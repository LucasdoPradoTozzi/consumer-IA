<?php

namespace App\Console\Commands;

use App\Models\JobApplication;
use App\Services\Workers\GenerationWorker;
use Illuminate\Console\Command;

class GeneratePendingApplications extends Command
{
    protected $signature = 'app:generate-pending-applications';

    protected $description = "Generate application materials for jobs with high scores (>= 70) that don't have versions yet";

    public function handle(): int
    {
        $lock = cache()->lock('generate-pending-applications-lock', 600);
        if (!$lock->get()) {
            $this->warn('Another instance is already running. Aborting.');
            return 0;
        }

        try {
            $this->info('Starting batch generation of pending applications...');

            // First, retry any incomplete versions
            $this->processIncompleteVersions();

            // Then process new high-score jobs that don't have a version yet
            $pendingJobs = JobApplication::where('match_score', '>=', 70)
                ->whereDoesntHave('versions')
                ->whereNotIn('status', [
                    JobApplication::STATUS_FAILED,
                    JobApplication::STATUS_REJECTED,
                ])
                ->get();

            $this->info("Found {$pendingJobs->count()} jobs with pending generations to process.");

            foreach ($pendingJobs as $jobApplication) {
                $this->info("Processing generation for job ID: {$jobApplication->id} (score: {$jobApplication->match_score})");

                try {
                    $generationWorker = app(GenerationWorker::class);
                    $generationWorker->process($jobApplication);
                    $this->info("Generation completed for job ID: {$jobApplication->id}");
                } catch (\Exception $e) {
                    $this->error("Error generating application for job ID {$jobApplication->id}: " . $e->getMessage());
                }
            }

            $this->info('Batch generation completed.');
        } finally {
            $lock->release();
        }

        return 0;
    }

    private function processIncompleteVersions(): void
    {
        $this->info('Checking for incomplete JobApplicationVersions...');

        $incompleteVersions = \App\Models\JobApplicationVersion::where(function ($q) {
            $q->whereNull('cover_letter')
              ->orWhereNull('resume_data')
              ->orWhereNull('resume_path')
              ->orWhere('completed', false);
        })->get();

        $this->info("Found {$incompleteVersions->count()} incomplete JobApplicationVersions.");

        foreach ($incompleteVersions as $version) {
            $jobApplication = $version->jobApplication;
            if (!$jobApplication) {
                $this->error("Missing jobApplication for version ID: {$version->id}");
                continue;
            }

            $this->info("Attempting to complete version ID: {$version->id} for job ID: {$jobApplication->id}");

            try {
                $generationWorker = app(GenerationWorker::class);
                $generationWorker->process($jobApplication);
                $this->info("Completed version ID: {$version->id}");
            } catch (\Exception $e) {
                $this->error("Error completing version ID {$version->id}: " . $e->getMessage());
            }
        }
    }
}
