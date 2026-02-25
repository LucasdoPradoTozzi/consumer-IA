<?php

namespace App\Services\Workers;

use App\Models\JobApplication;
use App\Services\LlmService;
use App\Services\PromptBuilderService;
use Illuminate\Support\Facades\Log;

class AnalyzeWorker
{
    public function __construct(
        private readonly LlmService $llm,
        private readonly PromptBuilderService $promptBuilder,
    ) {}

    /**
     * Analyze a job application — extracts structured job info and scores the candidate
     * in a single LLM call. Saves results directly onto the JobApplication record.
     */
    public function process(JobApplication $jobApplication): void
    {
        $startTime = microtime(true);
        $jobData = $jobApplication->job_data ?? [];

        Log::info('[AnalyzeWorker] Starting analyze', [
            'application_id' => $jobApplication->id,
        ]);

        if (empty($jobData['description'])) {
            $jobData['description'] = '[SEM DESCRIÇÃO]';
        }

        $candidateProfile = config('candidate.profile')();

        if (empty($candidateProfile)) {
            Log::warning('[AnalyzeWorker] Candidate profile is empty — ignoring job application', [
                'job_id' => $jobApplication->id,
            ]);
            
            $jobApplication->update([
                'status' => JobApplication::STATUS_FAILED,
                'scoring_data' => ['justification' => 'The candidate profile is empty. Job ignored.'],
            ]);
            return;
        }

        try {
            $prompt = $this->promptBuilder->buildAnalyzePrompt($jobData, $candidateProfile);

            if (empty(trim($prompt))) {
                Log::warning('[AnalyzeWorker] Prompt is empty — skipping LLM call', [
                    'job_id' => $jobApplication->id,
                ]);
                return;
            }

            $response = $this->llm->generateText($prompt);

            if (!is_string($response)) {
                Log::error('[AnalyzeWorker] LLM response is not a string', [
                    'type'     => gettype($response),
                    'response' => $response,
                ]);
                throw new \RuntimeException('LLM response is not a string');
            }

            $data = $this->parseJsonResponse($response, (string) $jobApplication->id);

            $extracted = $data['extracted_info'] ?? [];
            $scoring   = $data['scoring']        ?? [];

            // Persist extracted info and score directly on the job
            $jobApplication->update([
                'extracted_title'       => $extracted['title']           ?? null,
                'extracted_company'     => $extracted['company']         ?? null,
                'extracted_description' => $extracted['description']     ?? null,
                'required_skills'       => $extracted['required_skills'] ?? null,
                'extracted_location'    => $extracted['location']        ?? null,
                'extracted_salary'      => $extracted['salary']          ?? null,
                'employment_type'       => $extracted['employment_type'] ?? null,
                'language'              => $extracted['language']        ?? null,
                'company_data'          => $extracted['company_data']    ?? null,
                'extra_information'     => $extracted['extra_information'] ?? null,
                'match_score'           => $scoring['score']             ?? null,
                'scoring_data'          => $scoring ?: null,
                'status'                => JobApplication::STATUS_SCORED,
            ]);

            $duration = microtime(true) - $startTime;

            Log::info('[AnalyzeWorker] Analyze completed', [
                'job_id'   => $jobApplication->id,
                'score'    => $scoring['score'] ?? null,
                'language' => $extracted['language'] ?? 'unknown',
                'duration' => round($duration, 2),
            ]);
        } catch (\Exception $e) {
            Log::error('[AnalyzeWorker] Analyze failed', [
                'job_id' => $jobApplication->id,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
            ]);

            $jobApplication->update(['status' => JobApplication::STATUS_FAILED]);

            throw $e;
        }
    }

    /**
     * Parse and validate the unified JSON response from the LLM.
     */
    private function parseJsonResponse(string $response, string $jobId): array
    {
        $trimmed = trim($response);

        // Strip markdown code fences if present
        $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed);
        $trimmed = preg_replace('/\s*```\s*$/', '', $trimmed);
        $trimmed = trim($trimmed);

        $start = strpos($trimmed, '{');
        $end   = strrpos($trimmed, '}');

        if ($start === false || $end === false) {
            Log::error('[AnalyzeWorker] No JSON found in response', [
                'job_id'  => $jobId,
                'preview' => mb_substr($response, 0, 500),
            ]);
            throw new \Exception('No JSON found in response');
        }

        $json = substr($trimmed, $start, $end - $start + 1);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('[AnalyzeWorker] JSON parse failed', [
                'job_id'    => $jobId,
                'json_error'=> json_last_error_msg(),
                'preview'   => mb_substr($json, 0, 1000),
            ]);
            throw new \Exception('Invalid JSON: ' . json_last_error_msg());
        }

        if (!isset($data['extracted_info']) || !isset($data['scoring'])) {
            throw new \Exception('Missing required keys in analyze response: expected extracted_info and scoring');
        }

        if (!isset($data['scoring']['score'])) {
            throw new \Exception('Missing score in scoring response');
        }

        return $data;
    }
}
