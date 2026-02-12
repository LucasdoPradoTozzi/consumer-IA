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
}
