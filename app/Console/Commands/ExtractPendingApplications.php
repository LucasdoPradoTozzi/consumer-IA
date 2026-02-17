<?php

namespace App\Console\Commands;

use App\Models\JobApplication;
use App\Models\JobExtraction;
use App\Services\Workers\ExtractionWorker;
use Illuminate\Console\Command;

class ExtractPendingApplications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:extract-pending-applications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process OCR extraction for pending job extractions that have images';

    /**
     * Execute the console command.
     *
     * Finds JobExtractions that have extraction_data with base64 images
     * and runs the ExtractionWorker to process OCR via Ollama.
     * For extractions without images, extraction_data is already populated
     * with job_data from the deduplication step.
     */
    public function handle()
    {
        $lock = cache()->lock('extract-pending-applications-lock', 600);
        if (!$lock->get()) {
            $this->warn('Another instance is already running. Aborting.');
            return 0;
        }

        try {
            $this->info('Starting batch extraction of pending applications...');

            // 1. Backfill: Create initial extractions for jobs that don't have one yet
            $jobsWithoutExtraction = JobApplication::doesntHave('extractions')
                ->where('status', '!=', JobApplication::STATUS_FAILED)
                ->where('status', '!=', JobApplication::STATUS_REJECTED)
                ->get();

            if ($jobsWithoutExtraction->isNotEmpty()) {
                $this->info("Found {$jobsWithoutExtraction->count()} jobs without extraction. Creating initial records...");
                foreach ($jobsWithoutExtraction as $job) {
                    try {
                        $job->extractions()->create([
                            'version_number' => 1,
                            'extra_information' => null,
                            'extraction_data' => $job->job_data,
                        ]);
                        $this->info("Created initial extraction for job ID: {$job->id}");
                    } catch (\Exception $e) {
                        $this->error("Failed to create extraction for job ID {$job->id}: " . $e->getMessage());
                    }
                }
            }

            // We detect pending extraction by checking if extraction_data contains 'image' key
            // OR if it's missing the 'language' key (meaning it hasn't been enriched by the LLM yet)
            // OR if it's completely NULL (initial state from JobProcessorService)
            $pendingExtractions = JobExtraction::where(function ($q) {
                    $q->whereNull('extraction_data')
                      ->orWhereRaw("extraction_data::text LIKE '%\"image\"%'")
                      ->orWhereRaw("extraction_data->>'language' IS NULL");
                })
                ->get();

            $this->info("Found {$pendingExtractions->count()} extractions with images to process.");

            foreach ($pendingExtractions as $extraction) {
                $extractionData = $extraction->extraction_data ?? [];

                $jobApplication = $extraction->jobApplication;
                if (!$jobApplication) {
                    $this->error("JobApplication not found for extraction ID: {$extraction->id}");
                    continue;
                }

                $this->info("Processing OCR extraction for job application ID: {$jobApplication->id}, extraction version: {$extraction->version_number}");

                try {
                    $extractionWorker = app(ExtractionWorker::class);
                    $extractionWorker->process($jobApplication);
                    $this->info("Extraction completed for job application ID: {$jobApplication->id}");
                } catch (\Exception $e) {
                    $this->error("Error extracting for job application ID {$jobApplication->id}: " . $e->getMessage());
                }
            }

            $this->info('Batch extraction completed.');
        } finally {
            $lock->release();
        }

        return 0;
    }
}
