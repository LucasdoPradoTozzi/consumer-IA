<?php

namespace App\Console\Commands;

use App\Models\JobApplication;
use App\Services\Workers\AnalyzeWorker;
use Illuminate\Console\Command;

class AnalyzePendingApplications extends Command
{
    protected $signature = 'app:analyze-pending-applications';

    protected $description = 'Extract job info and score candidate compatibility for all pending job applications (single LLM call per job).';

    public function handle(): int
    {
        $lock = cache()->lock('analyze-pending-applications-lock', 600);

        if (!$lock->get()) {
            $this->warn('Another instance is already running. Aborting.');
            return 0;
        }

        try {
            $this->info('Starting batch analysis of pending applications...');

            // Jobs that haven't been analyzed yet (no match_score) and are not terminal states
            $pending = JobApplication::whereNull('match_score')
                ->whereNotIn('status', [
                    JobApplication::STATUS_FAILED,
                    JobApplication::STATUS_REJECTED,
                    JobApplication::STATUS_COMPLETED,
                ])
                ->get();

            $this->info("Found {$pending->count()} applications to analyze.");

            foreach ($pending as $jobApplication) {
                $this->info("Analyzing job application ID: {$jobApplication->id}");

                try {
                    $analyzeWorker = app(AnalyzeWorker::class);
                    $analyzeWorker->process($jobApplication);
                    $this->info("Done: job ID {$jobApplication->id} — score: {$jobApplication->fresh()->match_score}");
                } catch (\Exception $e) {
                    $this->error("Error analyzing job ID {$jobApplication->id}: " . $e->getMessage());
                }
            }

            $this->info('Batch analysis completed.');
        } finally {
            $lock->release();
        }

        return 0;
    }
}
