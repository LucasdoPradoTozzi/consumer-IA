<?php

namespace App\Console\Commands;

use App\DTO\JobPayload;
use App\Services\JobCoordinatorService;
use Illuminate\Console\Command;

class ProcessJobApplication extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'job:process {--job-data=} {--candidate-data=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process a job application through the granular worker pipeline';

    public function __construct(
        private readonly JobCoordinatorService $jobCoordinator,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $lock = cache()->lock('process-job-application-lock', 600);
        if (!$lock->get()) {
            $this->warn('Another instance is already running. Aborting.');
            return 0;
        }

        try {
            $jobDataJson = $this->option('job-data');
            $candidateDataJson = $this->option('candidate-data');

            if (!$jobDataJson) {
                $this->error('Job data is required. Use --job-data=\'{"title":"...", "company":"...", "link":"..."}\'');
                return 1;
            }

            $jobData = json_decode($jobDataJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Invalid job data JSON: ' . json_last_error_msg());
                return 1;
            }

            $candidateData = [];
            if ($candidateDataJson) {
                $candidateData = json_decode($candidateDataJson, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->error('Invalid candidate data JSON: ' . json_last_error_msg());
                    return 1;
                }
            } else {
                // Use default candidate profile
                $candidateData = config('candidate.profile')();
            }

            // Generate job ID from link or create unique ID
            $payload = new JobPayload(
                jobId: null,
                type: 'job_application',
                data: [
                    'job' => $jobData,
                    'candidate' => $candidateData,
                ],
                callbackUrl: null,
                priority: null
            );

            $this->info("Sending job to deduplication queue...");
            $this->info("Job Title: " . ($jobData['title'] ?? 'N/A'));
            $this->info("Company: " . ($jobData['company'] ?? 'N/A'));

            $this->jobCoordinator->sendToDeduplication($payload);
            $this->info('Job sent successfully to deduplication queue!');
        } catch (\Exception $e) {
            $this->error('Failed to process job: ' . $e->getMessage());
            return 1;
        } finally {
            $lock->release();
        }

        return 0;
    }
}

