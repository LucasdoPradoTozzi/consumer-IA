<?php

namespace App\Console\Commands\partials;

use App\Models\JobApplicationVersion;
use App\Services\Workers\GenerationWorker;
use Illuminate\Console\Command;

trait ProcessIncompleteJobApplicationVersions
{
    public function processIncompleteVersions(Command $command)
    {
        $command->info('Checking for incomplete JobApplicationVersions...');
                $incompleteVersions = JobApplicationVersion::where('completed', false)->get();

        $command->info('Found ' . $incompleteVersions->count() . ' incomplete JobApplicationVersions.');

        foreach ($incompleteVersions as $version) {
            $jobApplication = $version->jobApplication;
            $scoring = $version->scoring;
            if (!$jobApplication || !$scoring) {
                $command->error('Missing jobApplication or scoring for version ID: ' . $version->id);
                continue;
            }
            $command->info('Attempting to complete version ID: ' . $version->id . ' for job ID: ' . $jobApplication->job_id);
            try {
                $generationWorker = app(GenerationWorker::class);
                $generationWorker->process($jobApplication);
                $command->info('Completed version ID: ' . $version->id);
            } catch (\Exception $e) {
                $command->error('Error completing version ID: ' . $version->id . ': ' . $e->getMessage());
            }
        }
    }
}
