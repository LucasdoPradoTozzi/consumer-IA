<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RunPipeline extends Command
{
    protected $signature = 'app:run-pipeline {--stop-on-failure : Stop the pipeline if any step fails}';

    protected $description = 'Run the full application pipeline sequentially (analyze → generate → email) with a global lock to prevent concurrent executions.';

    /**
     * The pipeline steps in execution order.
     */
    private const STEPS = [
        'app:analyze-pending-applications',
        'app:generate-pending-applications',
        'app:send-pending-application-emails',
    ];

    public function handle(): int
    {
        $lock = cache()->lock('run-pipeline-lock', 1800);

        if (! $lock->get()) {
            $this->warn('⚠ Pipeline already running. Aborting.');
            return 0;
        }

        try {
            $this->info('🚀 Starting full pipeline...');
            $this->newLine();

            $failed = [];

            foreach (self::STEPS as $index => $command) {
                $step = $index + 1;
                $total = count(self::STEPS);

                $this->info("▶ [{$step}/{$total}] Running: {$command}");

                try {
                    $exitCode = $this->call($command);
                } catch (\Throwable $e) {
                    $this->error("✖ [{$step}/{$total}] Exception in {$command}: {$e->getMessage()}");
                    $failed[] = $command;

                    if ($this->option('stop-on-failure')) {
                        $this->error('⛔ --stop-on-failure is set. Halting pipeline.');
                        return 1;
                    }

                    continue;
                }

                if ($exitCode !== 0) {
                    $this->warn("⚠ [{$step}/{$total}] {$command} exited with code {$exitCode}");
                    $failed[] = $command;

                    if ($this->option('stop-on-failure')) {
                        $this->error('⛔ --stop-on-failure is set. Halting pipeline.');
                        return 1;
                    }
                } else {
                    $this->info("✔ [{$step}/{$total}] Finished: {$command}");
                }

                $this->newLine();
            }

            if (! empty($failed)) {
                $this->warn('⚠ Pipeline completed with failures: ' . implode(', ', $failed));
                return 1;
            }

            $this->info('✅ Pipeline completed successfully.');
            return 0;
        } finally {
            $lock->release();
        }
    }
}
