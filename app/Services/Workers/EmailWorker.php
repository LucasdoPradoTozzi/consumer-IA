<?php

namespace App\Services\Workers;

use App\Models\JobApplication;
use App\Models\JobApplicationVersion;
use App\Services\EmailService;
use Illuminate\Support\Facades\Log;

class EmailWorker
{
    public function __construct(
        private readonly EmailService $emailService,
    ) {}

    /**
     * Generate PDFs and send email
     *
     * @param JobApplication $jobApplication
     * @return void
     */
    public function process(JobApplication $jobApplication): void
    {
        // Find versions not sent
        $unsentVersions = $jobApplication->versions()
            ->where('email_sent', false)
            ->get();

        if ($unsentVersions->isEmpty()) {
            Log::info('[EmailWorker] No emails to send', [
                'job_id' => $jobApplication->id,
            ]);
            return;
        }

        foreach ($unsentVersions as $version) {
            $this->processVersion($jobApplication, $version);
        }
    }

    public function processVersion(JobApplication $jobApplication, JobApplicationVersion $version): void
    {
        $startTime = microtime(true);
        $jobData = $version->scoring->extraction->extraction_data ?? [];
        $candidateProfile = config('candidate.profile')();

        Log::info('[EmailWorker] Starting email for version', [
            'job_id' => $jobApplication->id,
            'version_id' => $version->id,
        ]);

        try {
            // Send email using the PDFs from the version
            $this->emailService->sendApplication(
                $jobApplication,
                $version->cover_letter,
                $version->resume_path,
                $version->email_subject,
                $version->email_body
            );

            $version->update([
                'email_sent' => true,
                'completed' => true,
            ]);

            $duration = microtime(true) - $startTime;

            Log::info('[EmailWorker] Email sent for version', [
                'job_id' => $jobApplication->id,
                'version_id' => $version->id,
                'duration' => round($duration, 2),
            ]);
        } catch (\Exception $e) {
            Log::error('[EmailWorker] Email failed for version', [
                'job_id' => $jobApplication->id,
                'version_id' => $version->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
