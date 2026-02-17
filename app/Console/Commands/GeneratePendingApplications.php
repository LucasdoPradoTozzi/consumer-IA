<?php

namespace App\Console\Commands;

use App\Models\JobApplication;
use App\Models\JobScoring;
use App\Services\Workers\GenerationWorker;
use Illuminate\Console\Command;
use App\Console\Commands\partials\ProcessIncompleteJobApplicationVersions;

class GeneratePendingApplications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-pending-applications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate application materials for jobs with high scores that don\'t have versions yet';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $lock = cache()->lock('generate-pending-applications-lock', 600);
        if (!$lock->get()) {
            $this->warn('Another instance is already running. Aborting.');
            return 0;
        }
        try {
            $this->info('Starting batch generation of pending applications...');
            // First, process incomplete JobApplicationVersions
            $this->processIncompleteVersions();
            // Then, process new pending jobs as before
            $pendingJobIds = JobScoring::where('scoring_score', '>=', 70)
                ->whereDoesntHave('versions')
                ->distinct()
                ->pluck('job_application_id');

            $this->info("Found {$pendingJobIds->count()} jobs with pending generations to process.");

            foreach ($pendingJobIds as $jobId) {
                $jobApplication = JobApplication::find($jobId);

                if (!$jobApplication) {
                    $this->error("JobApplication with ID {$jobId} not found.");
                    continue;
                }

                $this->info("Processing generation for job ID: {$jobApplication->id}");

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
    }

    public function processIncompleteVersions()
    {
        $this->info('Checking for incomplete JobApplicationVersions...');
        $incompleteVersions = \App\Models\JobApplicationVersion::where(function ($q) {
            $q->whereNull('cover_letter')
                ->orWhereNull('resume_data')
                ->orWhereNull('resume_path')
                ->orWhere('completed', false);
        })->get();

        $this->info('Found ' . $incompleteVersions->count() . ' incomplete JobApplicationVersions.');

        foreach ($incompleteVersions as $version) {
            $jobApplication = $version->jobApplication;
            $scoring = $version->scoring;
            if (!$jobApplication || !$scoring) {
                $this->error('Missing jobApplication or scoring for version ID: ' . $version->id);
                continue;
            }
            $this->info('Attempting to complete version ID: ' . $version->id . ' for job ID: ' . $jobApplication->id);
            try {
                $generationWorker = app(\App\Services\Workers\GenerationWorker::class);
                $generationWorker->process($jobApplication);
                $this->info('Completed version ID: ' . $version->id);
            } catch (\Exception $e) {
                $this->error('Error completing version ID: ' . $version->id . ': ' . $e->getMessage());
            }
        }
    }
}
