<?php

namespace App\Services\Workers;

use App\Models\JobApplication;
use App\Models\JobExtraction;
use App\Models\JobScoring;
use App\Services\LlmService;
use App\Services\PromptBuilderService;
use Illuminate\Support\Facades\Log;

class ScoringWorker
{
    public function __construct(
        private readonly LlmService $llm,
        private readonly PromptBuilderService $promptBuilder,
    ) {}

    /**
     * Score job compatibility
     *
     * @param JobApplication $jobApplication
     * @return void
     */
    public function process(JobApplication $jobApplication): void
    {
        // Find extractions with data but no scoring
        $extractionsWithoutScoring = $jobApplication->extractions()
            ->whereNotNull('extraction_data')
            ->whereDoesntHave('scorings')
            ->get();

        if ($extractionsWithoutScoring->isEmpty()) {
            Log::info('[ScoringWorker] No extractions to score', [
                'application_id' => $jobApplication->id,
            ]);
            return;
        }

        foreach ($extractionsWithoutScoring as $extraction) {
            $this->processExtraction($jobApplication, $extraction);
        }
    }

    private function processExtraction(JobApplication $jobApplication, JobExtraction $extraction): void
    {
        $startTime = microtime(true);
        $jobData = $extraction->extraction_data ?? [];
        $candidateProfile = config('candidate.profile')();

        Log::info('[ScoringWorker] Starting scoring for extraction', [
            'application_id' => $jobApplication->id,
            'extraction_version' => $extraction->version_number,
        ]);

        try {
            $prompt = $this->promptBuilder->buildScorePrompt($jobData, $candidateProfile);
            $response = $this->llm->generateText($prompt);

            if (!is_string($response)) {
                $response = json_encode($response);
            }

            $data = $this->parseJsonResponse($response, (string) $jobApplication->id);

            // Create scoring record
            $jobApplication->scorings()->create([
                'extraction_version_id' => $extraction->id,
                'scoring_score' => $data['score'],
                'scoring_data' => $data,
            ]);

            $duration = microtime(true) - $startTime;

            Log::info('[ScoringWorker] Scoring completed for extraction', [
                'job_id' => $jobApplication->id,
                'extraction_version' => $extraction->version_number,
                'score' => $data['score'],
                'duration' => round($duration, 2),
            ]);
        } catch (\Exception $e) {
            Log::error('[ScoringWorker] Scoring failed for extraction', [
                'job_id' => $jobApplication->id,
                'extraction_version' => $extraction->version_number,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Parse JSON response from LLM
     */
    private function parseJsonResponse(string $response, string $jobId): array
    {
        $trimmed = trim($response);

        // Strip markdown code fences if present (```json ... ```)
        $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed);
        $trimmed = preg_replace('/\s*```\s*$/', '', $trimmed);
        $trimmed = trim($trimmed);

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');

        if ($start === false || $end === false) {
            Log::error('[ScoringWorker] No JSON found in LLM response', [
                'job_id' => $jobId,
                'response_preview' => mb_substr($response, 0, 500),
            ]);
            throw new \Exception('No JSON found in response');
        }

        $json = substr($trimmed, $start, $end - $start + 1);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('[ScoringWorker] JSON parse failed', [
                'job_id' => $jobId,
                'json_error' => json_last_error_msg(),
                'extracted_json_preview' => mb_substr($json, 0, 1000),
                'raw_response_preview' => mb_substr($response, 0, 500),
            ]);
            throw new \Exception('Invalid JSON: ' . json_last_error_msg());
        }

        if (!isset($data['score']) || !isset($data['justification'])) {
            throw new \Exception('Missing required fields in scoring response');
        }

        return $data;
    }
}
