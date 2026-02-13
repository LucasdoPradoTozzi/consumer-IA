<?php

namespace App\Services;

use App\DTO\JobPayload;
use App\Models\JobApplication;
use Illuminate\Support\Facades\Log;

class JobProcessorService
{
    public function __construct(
        private readonly OllamaService $ollama,
        private readonly PromptBuilderService $promptBuilder,
        private readonly PdfService $pdfService,
        private readonly EmailService $emailService,
    ) {}

    /**
     * Process a job from RabbitMQ
     *
     * @param JobPayload $payload
     * @return void
     * @throws \Exception
     */
    public function process(JobPayload $payload): void
    {
        $startTime = microtime(true);

        Log::info('[JobProcessor] Starting job processing', [
            'job_id' => $payload->jobId,
            'type' => $payload->type,
        ]);

        match ($payload->type) {
            'job_application' => $this->processJobApplication($payload),
            default => throw new \InvalidArgumentException("Unknown job type: {$payload->type}"),
        };

        $totalTime = microtime(true) - $startTime;

        Log::info('[JobProcessor] Job processed successfully', [
            'job_id' => $payload->jobId,
            'total_time' => round($totalTime, 2),
        ]);
    }

    /**
     * Process job application
     *
     * @param JobPayload $payload
     * @return void
     * @throws \Exception
     */
    private function processJobApplication(JobPayload $payload): void
    {
        $startTime = microtime(true);
        $jobData = $payload->data['job'] ?? [];
        $candidateProfile = $payload->data['candidate'] ?? [];
        $imageBase64 = $payload->data['image'] ?? null;

        // Create or update job application record
        $jobApplication = JobApplication::updateOrCreate(
            ['job_id' => $payload->jobId],
            [
                'type' => $payload->type,
                'status' => JobApplication::STATUS_PROCESSING,
                'job_title' => $jobData['title'] ?? null,
                'job_company' => $jobData['company'] ?? null,
                'job_description' => $jobData['description'] ?? null,
                'job_skills' => $jobData['required_skills'] ?? null,
                'job_data' => $jobData,
                'candidate_name' => $candidateProfile['name'] ?? null,
                'candidate_email' => $candidateProfile['email'] ?? null,
                'candidate_data' => $candidateProfile,
                'started_at' => now(),
                'metadata' => $payload->metadata,
            ]
        );

        try {
            // Step 1: Extract text from image if provided
            $extractedText = null;
            if ($imageBase64) {
                $extractedText = $this->extractTextFromImage($imageBase64, $payload->jobId);
            }

            // Step 2: Classification
            $classification = $this->classifyJob($jobData, $extractedText, $payload->jobId);

            $jobApplication->update([
                'status' => JobApplication::STATUS_CLASSIFIED,
                'is_relevant' => $classification['is_relevant'],
                'classification_reason' => $classification['reason'],
                'classified_at' => now(),
            ]);

            if (!$classification['is_relevant']) {
                $jobApplication->update([
                    'status' => JobApplication::STATUS_REJECTED,
                    'processing_time_seconds' => round(microtime(true) - $startTime),
                ]);

                Log::info('[JobProcessor] Job rejected as not relevant', [
                    'job_id' => $payload->jobId,
                    'reason' => $classification['reason'],
                ]);
                return;
            }

            // Step 3: Scoring
            $scoring = $this->scoreJobMatch($jobData, $candidateProfile, $payload->jobId);

            $jobApplication->update([
                'status' => JobApplication::STATUS_SCORED,
                'match_score' => $scoring['score'],
                'score_justification' => $scoring['justification'],
                'scored_at' => now(),
            ]);

            $threshold = config('processing.score_threshold');

            Log::info('[JobProcessor] Job scored', [
                'job_id' => $payload->jobId,
                'score' => $scoring['score'],
                'threshold' => $threshold,
                'justification' => $scoring['justification'],
            ]);

            if ($scoring['score'] < $threshold) {
                $jobApplication->update([
                    'status' => JobApplication::STATUS_REJECTED,
                    'processing_time_seconds' => round(microtime(true) - $startTime),
                ]);

                Log::info('[JobProcessor] Score below threshold, skipping generation', [
                    'job_id' => $payload->jobId,
                    'score' => $scoring['score'],
                    'threshold' => $threshold,
                ]);
                return;
            }

            // Step 4: Generate cover letter
            $coverLetter = $this->generateCoverLetter($jobData, $candidateProfile, $payload->jobId);

            // Step 5: Adjust resume
            $resumeAdjustment = $this->adjustResume($jobData, $candidateProfile, $payload->jobId);

            $jobApplication->update([
                'cover_letter' => $coverLetter['cover_letter'],
                'adjusted_resume' => $resumeAdjustment['adjusted_resume'],
                'resume_changes' => $resumeAdjustment['changes_made'],
            ]);

            Log::info('[JobProcessor] Application materials generated', [
                'job_id' => $payload->jobId,
                'cover_letter_length' => strlen($coverLetter['cover_letter']),
                'resume_changes' => count($resumeAdjustment['changes_made']),
            ]);

            // Step 6: Generate PDFs
            $coverLetterPdfPath = $this->pdfService->generateCoverLetterPdf(
                $coverLetter['cover_letter'],
                $jobData,
                $candidateProfile,
                $payload->jobId
            );

            $resumePdfPath = $this->pdfService->generateResumePdf(
                $resumeAdjustment['adjusted_resume'],
                $candidateProfile,
                $payload->jobId
            );

            $jobApplication->update([
                'cover_letter_pdf_path' => $coverLetterPdfPath,
                'resume_pdf_path' => $resumePdfPath,
            ]);

            Log::info('[JobProcessor] PDFs generated', [
                'job_id' => $payload->jobId,
                'cover_letter_pdf' => $coverLetterPdfPath,
                'resume_pdf' => $resumePdfPath,
            ]);

            // Step 7: Send email with attachments
            $this->emailService->sendRecommendation(
                $payload,
                $coverLetterPdfPath,
                $resumePdfPath,
                [
                    'score' => $scoring['score'],
                    'justification' => $scoring['justification'],
                ]
            );

            $jobApplication->update([
                'status' => JobApplication::STATUS_COMPLETED,
                'email_sent' => true,
                'email_sent_at' => now(),
                'completed_at' => now(),
                'processing_time_seconds' => round(microtime(true) - $startTime),
            ]);

            Log::info('[JobProcessor] Email sent successfully', [
                'job_id' => $payload->jobId,
            ]);
        } catch (\Exception $e) {
            // Save error to database
            $jobApplication->update([
                'status' => JobApplication::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'failed_at' => now(),
                'processing_time_seconds' => round(microtime(true) - $startTime),
            ]);

            // Re-throw exception to prevent ACK
            throw $e;
        }
    }

    /**
     * Extract text from image using Ollama vision
     *
     * @param string $imageBase64
     * @param string $jobId
     * @return string
     * @throws \Exception
     */
    private function extractTextFromImage(string $imageBase64, string $jobId): string
    {
        $stepStart = microtime(true);

        Log::info('[JobProcessor] Extracting text from image', [
            'job_id' => $jobId,
        ]);

        $prompt = <<<PROMPT
Extract all text visible in this image. Return the text exactly as it appears, maintaining formatting where possible.
Return ONLY the extracted text, no additional commentary.
PROMPT;

        $response = $this->ollama->generate($prompt, [$imageBase64]);

        $duration = microtime(true) - $stepStart;

        Log::info('[JobProcessor] Text extraction completed', [
            'job_id' => $jobId,
            'duration' => round($duration, 2),
            'extracted_length' => strlen($response),
        ]);

        return $response;
    }

    /**
     * Classify job as relevant or not
     *
     * @param array $jobData
     * @param string|null $extractedText
     * @param string $jobId
     * @return array
     * @throws \Exception
     */
    private function classifyJob(array $jobData, ?string $extractedText, string $jobId): array
    {
        $stepStart = microtime(true);

        Log::info('[JobProcessor] Step 1: Classifying job', [
            'job_id' => $jobId,
        ]);

        $prompt = $this->promptBuilder->buildClassificationPrompt($jobData, $extractedText);
        $response = $this->ollama->generate($prompt);

        $data = $this->parseJsonResponse($response, 'classification', $jobId);

        $duration = microtime(true) - $stepStart;

        Log::info('[JobProcessor] Classification completed', [
            'job_id' => $jobId,
            'duration' => round($duration, 2),
            'is_relevant' => $data['is_relevant'],
            'reason' => $data['reason'],
        ]);

        return $data;
    }

    /**
     * Score job-candidate match
     *
     * @param array $jobData
     * @param array $candidateProfile
     * @param string $jobId
     * @return array
     * @throws \Exception
     */
    private function scoreJobMatch(array $jobData, array $candidateProfile, string $jobId): array
    {
        $stepStart = microtime(true);

        Log::info('[JobProcessor] Step 2: Scoring job match', [
            'job_id' => $jobId,
        ]);

        $prompt = $this->promptBuilder->buildScorePrompt($jobData, $candidateProfile);
        $response = $this->ollama->generate($prompt);

        $data = $this->parseJsonResponse($response, 'scoring', $jobId);

        $duration = microtime(true) - $stepStart;

        Log::info('[JobProcessor] Scoring completed', [
            'job_id' => $jobId,
            'duration' => round($duration, 2),
            'score' => $data['score'],
        ]);

        return $data;
    }

    /**
     * Generate cover letter
     *
     * @param array $jobData
     * @param array $candidateProfile
     * @param string $jobId
     * @return array
     * @throws \Exception
     */
    private function generateCoverLetter(array $jobData, array $candidateProfile, string $jobId): array
    {
        $stepStart = microtime(true);

        Log::info('[JobProcessor] Step 3: Generating cover letter', [
            'job_id' => $jobId,
        ]);

        $prompt = $this->promptBuilder->buildCoverLetterPrompt($jobData, $candidateProfile);
        $response = $this->ollama->generate($prompt);

        $data = $this->parseJsonResponse($response, 'cover_letter', $jobId);

        $duration = microtime(true) - $stepStart;

        Log::info('[JobProcessor] Cover letter generated', [
            'job_id' => $jobId,
            'duration' => round($duration, 2),
            'length' => strlen($data['cover_letter']),
        ]);

        return $data;
    }

    /**
     * Adjust resume for job
     *
     * @param array $jobData
     * @param array $candidateProfile
     * @param string $jobId
     * @return array
     * @throws \Exception
     */
    private function adjustResume(array $jobData, array $candidateProfile, string $jobId): array
    {
        $stepStart = microtime(true);

        Log::info('[JobProcessor] Step 4: Adjusting resume', [
            'job_id' => $jobId,
        ]);

        $prompt = $this->promptBuilder->buildResumeAdjustmentPrompt($jobData, $candidateProfile);
        $response = $this->ollama->generate($prompt);

        $data = $this->parseJsonResponse($response, 'resume_adjustment', $jobId);

        $duration = microtime(true) - $stepStart;

        Log::info('[JobProcessor] Resume adjustment completed', [
            'job_id' => $jobId,
            'duration' => round($duration, 2),
            'changes_count' => count($data['changes_made']),
        ]);

        return $data;
    }

    /**
     * Parse JSON response from LLM
     *
     * @param string $response
     * @param string $step
     * @param string $jobId
     * @return array
     * @throws \Exception
     */
    private function parseJsonResponse(string $response, string $step, string $jobId): array
    {
        // Try to extract JSON from response (in case LLM adds extra text)
        $trimmed = trim($response);

        // Find JSON object in response
        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');

        if ($start === false || $end === false) {
            Log::error('[JobProcessor] Invalid JSON response - no JSON found', [
                'job_id' => $jobId,
                'step' => $step,
                'response' => $response,
            ]);
            throw new \Exception("Invalid JSON response from LLM in step: {$step}");
        }

        $jsonString = substr($trimmed, $start, $end - $start + 1);

        $data = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('[JobProcessor] Failed to parse JSON response', [
                'job_id' => $jobId,
                'step' => $step,
                'error' => json_last_error_msg(),
                'response' => $response,
                'extracted_json' => $jsonString,
            ]);
            throw new \Exception("Failed to parse JSON in step {$step}: " . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Mark job as done (already applied)
     *
     * This is used when you've already applied for a job manually
     * and just want to mark it as completed in the system.
     *
     * @param string|int $jobId
     * @return void
     */
    public function markJobAsDone(string|int $jobId): void
    {
        $startTime = microtime(true);

        Log::info('[JobProcessor] Marking job as done', [
            'job_id' => $jobId,
        ]);

        // Find existing job application or create a minimal one
        $jobApplication = JobApplication::firstOrCreate(
            ['job_id' => (string)$jobId],
            [
                'type' => 'manual_application',
                'status' => JobApplication::STATUS_COMPLETED,
                'started_at' => now(),
                'completed_at' => now(),
            ]
        );

        // If it already exists, just mark as completed
        if (!$jobApplication->wasRecentlyCreated) {
            $jobApplication->update([
                'status' => JobApplication::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }

        $totalTime = microtime(true) - $startTime;

        Log::info('[JobProcessor] Job marked as done', [
            'job_id' => $jobId,
            'was_new' => $jobApplication->wasRecentlyCreated,
            'total_time' => round($totalTime, 2),
        ]);
    }

    /**
     * Reprocess job with additional message/feedback
     *
     * This allows you to reprocess a job taking into account additional
     * context or feedback. Useful for rejected jobs you want to reconsider
     * or jobs that need manual adjustments.
     *
     * @param string|int $jobId
     * @param string $message Additional context or feedback for reprocessing
     * @return void
     * @throws \Exception
     */
    public function reprocessJob(string|int $jobId, string $message): void
    {
        $startTime = microtime(true);

        Log::info('[JobProcessor] Starting job reprocessing', [
            'job_id' => $jobId,
            'message_length' => strlen($message),
        ]);

        // Find existing job application
        $jobApplication = JobApplication::where('job_id', (string)$jobId)->first();

        if (!$jobApplication) {
            throw new \Exception("Job application not found for job_id: {$jobId}");
        }

        // Store original status for logging
        $originalStatus = $jobApplication->status;

        // Reset to processing state
        $jobApplication->update([
            'status' => JobApplication::STATUS_PROCESSING,
            'reprocessing_message' => $message,
            'reprocessed_at' => now(),
            'started_at' => now(),
        ]);

        try {
            $jobData = $jobApplication->job_data ?? [];
            $candidateProfile = $jobApplication->candidate_data ?? [];

            // Re-classify with additional context
            $classification = $this->reclassifyWithMessage(
                $jobData,
                $message,
                $originalStatus,
                $jobId
            );

            $jobApplication->update([
                'status' => JobApplication::STATUS_CLASSIFIED,
                'is_relevant' => $classification['is_relevant'],
                'classification_reason' => $classification['reason'],
                'classified_at' => now(),
            ]);

            if (!$classification['is_relevant']) {
                $jobApplication->update([
                    'status' => JobApplication::STATUS_REJECTED,
                    'processing_time_seconds' => round(microtime(true) - $startTime),
                ]);

                Log::info('[JobProcessor] Job rejected after reprocessing', [
                    'job_id' => $jobId,
                    'reason' => $classification['reason'],
                ]);
                return;
            }

            // Re-score with new classification
            $scoring = $this->scoreJobMatch($jobData, $candidateProfile, $jobId);

            $jobApplication->update([
                'status' => JobApplication::STATUS_SCORED,
                'match_score' => $scoring['score'],
                'score_justification' => $scoring['justification'],
                'scored_at' => now(),
            ]);

            $threshold = config('processing.score_threshold');

            if ($scoring['score'] < $threshold) {
                $jobApplication->update([
                    'status' => JobApplication::STATUS_REJECTED,
                    'processing_time_seconds' => round(microtime(true) - $startTime),
                ]);

                Log::info('[JobProcessor] Job rejected on score after reprocessing', [
                    'job_id' => $jobId,
                    'score' => $scoring['score'],
                    'threshold' => $threshold,
                ]);
                return;
            }

            // Continue with full pipeline: Generate, PDFs, Email
            $generation = $this->generateCoverLetter($jobData, $candidateProfile, $scoring, $jobId);

            $jobApplication->update([
                'status' => JobApplication::STATUS_GENERATED,
                'cover_letter' => $generation['cover_letter'],
                'generated_at' => now(),
            ]);

            // Generate PDFs
            $pdfPaths = $this->generatePdfs($jobData, $candidateProfile, $generation['cover_letter'], $jobId);

            $jobApplication->update([
                'status' => JobApplication::STATUS_PDF_READY,
                'cover_letter_pdf_path' => $pdfPaths['cover_letter'],
                'resume_pdf_path' => $pdfPaths['resume'],
                'pdf_generated_at' => now(),
            ]);

            // Send email
            $this->sendApplicationEmail($jobApplication, $pdfPaths);

            // Final update
            $jobApplication->update([
                'status' => JobApplication::STATUS_COMPLETED,
                'completed_at' => now(),
                'processing_time_seconds' => round(microtime(true) - $startTime),
            ]);

            $totalTime = microtime(true) - $startTime;

            Log::info('[JobProcessor] Job reprocessed successfully', [
                'job_id' => $jobId,
                'original_status' => $originalStatus,
                'final_status' => JobApplication::STATUS_COMPLETED,
                'total_time' => round($totalTime, 2),
            ]);
        } catch (\Exception $e) {
            $jobApplication->update([
                'status' => JobApplication::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            Log::error('[JobProcessor] Failed to reprocess job', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Re-classify job with additional message context
     *
     * @param array $jobData
     * @param string $message
     * @param string $originalStatus
     * @param string|int $jobId
     * @return array{is_relevant: bool, reason: string}
     */
    private function reclassifyWithMessage(
        array $jobData,
        string $message,
        string $originalStatus,
        string|int $jobId
    ): array {
        Log::info('[JobProcessor] Re-classifying job with message', [
            'job_id' => $jobId,
            'original_status' => $originalStatus,
        ]);

        $prompt = $this->promptBuilder->buildReclassificationPrompt(
            $jobData,
            $message,
            $originalStatus
        );

        $response = $this->ollama->generate(
            prompt: $prompt,
            profile: 'conservative'
        );

        $data = $this->extractJsonFromResponse($response, $jobId, 'reclassification');

        if (!isset($data['is_relevant']) || !isset($data['reason'])) {
            throw new \Exception('Invalid reclassification response: missing is_relevant or reason');
        }

        Log::info('[JobProcessor] Re-classification complete', [
            'job_id' => $jobId,
            'is_relevant' => $data['is_relevant'],
        ]);

        return [
            'is_relevant' => (bool)$data['is_relevant'],
            'reason' => $data['reason'],
        ];
    }
}
