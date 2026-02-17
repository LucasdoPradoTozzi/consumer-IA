<?php

namespace App\Services;

use App\DTO\JobPayload;
use App\Models\JobApplication;
use App\Services\Workers\DeduplicationWorker;
use Illuminate\Support\Facades\Log;

class JobProcessorService
{
    public function __construct(
        private readonly DeduplicationWorker $deduplicationWorker,
    ) {}

    /**
     * Process a job from RabbitMQ.
     *
     * This service is responsible ONLY for:
     * 1. Creating a JobApplication record (deduplication check)
     * 2. Creating the initial JobExtraction record with job_data
     *
     * All subsequent processing (scoring, generation, email) is handled
     * by batch Artisan commands via cron/scheduler.
     *
     * @param JobPayload $payload
     * @param string|null $queueName
     * @param string|null $rawMessage
     * @return void
     * @throws \Exception
     */
    public function process(JobPayload $payload, ?string $queueName = null, ?string $rawMessage = null): void
    {
        $startTime = microtime(true);

        Log::info('[JobProcessor] Starting job processing', [
            'type' => $payload->type,
            'queue' => $queueName,
        ]);

        match ($payload->type) {
            'job_application' => $this->processDeduplication($payload, $rawMessage),
            default => throw new \InvalidArgumentException("Unknown job type: {$payload->type}"),
        };

        $totalTime = microtime(true) - $startTime;

        Log::info('[JobProcessor] Job processed successfully', [
            'total_time' => round($totalTime, 2),
        ]);
    }

    /**
     * Process deduplication: save JobApplication + initial JobExtraction.
     *
     * Flow:
     * 1. Create JobApplication with job_data and raw_message
     * 2. Run DeduplicationWorker to check for duplicates
     * 3. If new: create JobExtraction (version 1) with extraction_data = job_data
     * 4. Stop here — batch commands handle the rest (extraction OCR, scoring, generation, email)
     */
    private function processDeduplication(JobPayload $payload, ?string $rawMessage = null): void
    {
        $jobData = $payload->data['job'] ?? [];
        $candidateData = $payload->data['candidate'] ?? [];

        // Merge candidate data into job_data for downstream access
        $jobDataWithCandidate = array_merge($jobData, [
            'candidate_name' => $candidateData['name'] ?? null,
            'candidate_email' => $candidateData['email'] ?? null,
        ]);

        // Handle image: if present in payload data, include in job_data
        if (!empty($payload->data['image'])) {
            $jobDataWithCandidate['image'] = $payload->data['image'];
        }

        $jobApplication = new JobApplication([
            'status' => JobApplication::STATUS_PENDING,
            'raw_message' => $rawMessage,
            'job_data' => $jobDataWithCandidate,
        ]);

        $isNew = $this->deduplicationWorker->process($jobApplication);

        Log::info('[JobProcessorService] Resultado da deduplicação', [
            'isNew' => $isNew,
        ]);

        if ($isNew) {
            $jobApplication->save();
            
            Log::info('[JobProcessorService] JobApplication criado', [
                'id' => $jobApplication->id,
            ]);

            // Create initial extraction without data
            // This ensures worker will perform full enrichment via LLM
            $jobApplication->extractions()->create([
                'version_number' => 1,
                'extra_information' => null,
                'extraction_data' => null,
            ]);

            Log::info('[JobProcessorService] JobExtraction inicial criada com extraction_data', [
                'id' => $jobApplication->id,
                'extraction_data_keys' => array_keys($jobDataWithCandidate),
            ]);

            // Pipeline batch commands will handle the rest:
            // 1. app:extract-pending-applications (OCR if image present)
            // 2. app:score-pending-extractions
            // 3. app:generate-pending-applications
            // 4. app:send-pending-application-emails
        } else {
            Log::info('[JobProcessorService] Job duplicado, não será processado');
        }
    }

    /**
     * Mark job as done (already applied manually)
     */
    public function markJobAsDone(string $jobId, ?string $queueName = null, ?string $rawMessage = null): void
    {
        $jobApplication = JobApplication::find($jobId);

        if (!$jobApplication) {
            Log::error('[JobProcessorService] Job not found for markJobAsDone', ['job_id' => $jobId]);
            throw new \Exception("Job application not found: {$jobId}");
        }

        $jobApplication->update([
            'status' => JobApplication::STATUS_COMPLETED,
        ]);

        Log::info('[JobProcessorService] Job marked as done', [
            'id' => $jobApplication->id,
        ]);
    }

    /**
     * Reprocess job with additional feedback message
     */
    public function reprocessJob(string $jobId, string $message, ?string $queueName = null, ?string $rawMessage = null): void
    {
        $jobApplication = JobApplication::find($jobId);

        if (!$jobApplication) {
            Log::error('[JobProcessorService] Job not found for reprocessJob', ['job_id' => $jobId]);
            throw new \Exception("Job application not found: {$jobId}");
        }

        // Reset status to pending so batch commands will pick it up again
        $jobApplication->update([
            'status' => JobApplication::STATUS_PENDING,
        ]);

        // Create a new extraction version with the feedback as extra_information
        $latestVersion = $jobApplication->extractions()->max('version_number') ?? 0;
        $jobApplication->extractions()->create([
            'version_number' => $latestVersion + 1,
            'extra_information' => $message,
            'extraction_data' => $jobApplication->job_data,
        ]);

        Log::info('[JobProcessorService] Job reprocessado com feedback', [
            'id' => $jobApplication->id,
            'new_version' => $latestVersion + 1,
            'message_preview' => substr($message, 0, 100),
        ]);
    }
}
