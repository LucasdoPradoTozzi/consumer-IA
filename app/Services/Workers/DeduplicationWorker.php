<?php

namespace App\Services\Workers;

use App\Models\JobApplication;
use App\Models\JobDeduplication;
use Illuminate\Support\Facades\Log;

class DeduplicationWorker
{
    public function __construct() {}


    /**
     * Check if job is duplicate and handle accordingly
     *
     * @param JobApplication $jobApplication
     * @return bool True if new job, false if duplicate
     */
    public function process(JobApplication $jobApplication): bool
    {
        $startTime = microtime(true);
        $jobData = $jobApplication->job_data ?? [];
        $link = $jobData['link'] ?? null;
        $content = $jobApplication->raw_message ?? json_encode($jobData);

        Log::info('[DeduplicationWorker] Starting deduplication');

        try {

            $existingByLink = JobDeduplication::where('original_link', $link)->first();
            if ($existingByLink) {
                Log::info('[DeduplicationWorker] Duplicate job found by link (literal)', [
                    'link' => $link,
                    'existing_job_id' => $existingByLink->job_application_id,
                ]);
                return false;
            }

            $existingByContent = JobDeduplication::where('original_content', $content)->first();
            if ($existingByContent) {
                Log::info('[DeduplicationWorker] Duplicate job found by content (literal)', [
                    'content_length' => strlen($content),
                    'existing_job_id' => $existingByContent->job_application_id,
                ]);
                return false;
            }

            $jobApplication->save();

            JobDeduplication::create([
                'hash' => md5($link),
                'original_link' => $link,
                'original_content' => $content,
                'job_application_id' => $jobApplication->id,
                'first_seen_at' => now(),
            ]);

            $duration = microtime(true) - $startTime;
            Log::info('[DeduplicationWorker] Deduplication completed (new job)', [
                'duration' => round($duration, 2),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('[DeduplicationWorker] Deduplication failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
