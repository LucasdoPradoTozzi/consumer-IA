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
     *
     * All subsequent processing (analyze, generation, email) is handled
     * by batch Artisan commands via cron/scheduler.
     */
    public function process(JobPayload $payload, ?string $queueName = null, ?string $rawMessage = null): void
    {
        $startTime = microtime(true);

        Log::info('[JobProcessor] Starting job processing', [
            'type'  => $payload->type,
            'queue' => $queueName,
        ]);

        match ($payload->type) {
            'job_application' => $this->processDeduplication($payload, $rawMessage),
            default           => throw new \InvalidArgumentException("Unknown job type: {$payload->type}"),
        };

        Log::info('[JobProcessor] Job processed successfully', [
            'total_time' => round(microtime(true) - $startTime, 2),
        ]);
    }

    /**
     * Process deduplication: save JobApplication.
     *
     * Flow:
     * 1. Create JobApplication with job_data and raw_message
     * 2. Run DeduplicationWorker to check for duplicates
     * 3. If new: save the application and stop here
     *    The 'app:analyze-pending-applications' cron command will pick it up next.
     */
    private function processDeduplication(JobPayload $payload, ?string $rawMessage = null): void
    {
        $jobData       = $payload->data['job']       ?? [];
        $candidateData = $payload->data['candidate'] ?? [];

        $jobDataWithCandidate = array_merge($jobData, [
            'candidate_name'  => $candidateData['name']  ?? null,
            'candidate_email' => $candidateData['email'] ?? null,
        ]);

        if (!empty($payload->data['image'])) {
            $jobDataWithCandidate['image'] = $payload->data['image'];
        }

        $jobApplication = new JobApplication([
            'status'      => JobApplication::STATUS_PENDING,
            'raw_message' => $rawMessage,
            'job_data'    => $jobDataWithCandidate,
        ]);

        $isNew = $this->deduplicationWorker->process($jobApplication);

        Log::info('[JobProcessorService] Resultado da deduplicação', ['isNew' => $isNew]);

        if ($isNew) {
            Log::info('[JobProcessorService] JobApplication criado', [
                'id' => $jobApplication->id,
            ]);
            // The analyze cron command (app:analyze-pending-applications) will pick this up.
        } else {
            Log::info('[JobProcessorService] Job duplicado, não será processado');
        }
    }

    /**
     * Mark job as done (already applied manually)
     */
    public function markJobAsDone(string $jobId, ?string $queueName = null, ?string $rawMessage = null): void
    {
        $jobApplication = JobApplication::findOrFail($jobId);

        $jobApplication->update(['status' => JobApplication::STATUS_COMPLETED]);

        Log::info('[JobProcessorService] Job marked as done', ['id' => $jobApplication->id]);
    }

    /**
     * Reprocess job with additional feedback message
     */
    public function reprocessJob(string $jobId, string $message, ?string $queueName = null, ?string $rawMessage = null): void
    {
        $jobApplication = JobApplication::findOrFail($jobId);

        // Reset analysis fields so the analyze worker will re-process it
        $jobApplication->update([
            'status'                => JobApplication::STATUS_PENDING,
            'match_score'           => null,
            'scoring_data'          => null,
            'extracted_title'       => null,
            'extracted_company'     => null,
            'extracted_description' => null,
            'required_skills'       => null,
            'extracted_location'    => null,
            'extracted_salary'      => null,
            'employment_type'       => null,
            'language'              => null,
            'company_data'          => null,
            'extra_information'     => null,
        ]);

        // Store the feedback in job_data so the LLM can consider it during re-analysis
        $updatedJobData = $jobApplication->job_data ?? [];
        $updatedJobData['reprocess_feedback'] = $message;
        $jobApplication->update(['job_data' => $updatedJobData]);

        Log::info('[JobProcessorService] Job reprocessado com feedback', [
            'id'              => $jobApplication->id,
            'message_preview' => substr($message, 0, 100),
        ]);
    }
}
