<?php

namespace App\Console\Commands;

use App\Models\JobApplication;
use App\Models\JobExtraction;
use App\Services\Workers\ScoringWorker;
use Illuminate\Console\Command;

class ScorePendingExtractions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:score-pending-extractions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Score all pending extractions that do not have scores yet';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $lock = cache()->lock('score-pending-extractions-lock', 600);
        if (!$lock->get()) {
            $this->warn('Another instance is already running. Aborting.');
            return 0;
        }

        try {
            $this->info('Starting batch scoring of pending extractions...');

            // Find job_application_ids that have extractions without scorings
            $pendingJobIds = JobExtraction::whereNotNull('extraction_data')
                ->whereDoesntHave('scorings')
                ->distinct()
                ->pluck('job_application_id');

            $this->info("Found {$pendingJobIds->count()} jobs with pending extractions to score.");

            foreach ($pendingJobIds as $jobId) {
                $jobApplication = JobApplication::find($jobId);

                if (!$jobApplication) {
                    $this->error("JobApplication with ID {$jobId} not found.");
                    continue;
                }

                $this->info("Processing scoring for job application ID: {$jobApplication->id}");

                try {
                    $scoringWorker = app(ScoringWorker::class);
                    $scoringWorker->process($jobApplication);
                    $this->info("Scoring completed for job application ID: {$jobApplication->id}");
                } catch (\Exception $e) {
                    $this->error("Error scoring job application ID {$jobApplication->id}: " . $e->getMessage());
                }
            }

            $this->info('Batch scoring completed.');
        } finally {
            $lock->release();
        }

        return 0;
    }
}

