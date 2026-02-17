<?php

namespace App\Console\Commands;

use App\Services\RabbitConsumerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ConsumeRabbit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'worker:consume
                            {--once : Process only one message and exit}
                            {--timeout=50 : Maximum time in seconds to run (0 for unlimited)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consume messages from RabbitMQ queues (intake, mark-done, reprocess)';

    /**
     * Execute the console command.
     *
     * @param RabbitConsumerService $consumer
     * @return int
     */
    public function handle(RabbitConsumerService $consumer): int
    {
        $queues = config('rabbitmq.queues');
        $timeout = (int) $this->option('timeout');

        $descriptions = [
            'deduplication' => 'check for duplicates and store job',
            'mark-job-done' => 'mark as manually applied',
            'reproccess-job' => 'reprocess with feedback message',
        ];

        $this->info('Starting RabbitMQ consumer...');
        $this->info('Host: ' . config('rabbitmq.host'));
        $this->info('Queues:');
        foreach ($queues as $key => $queue) {
            $desc = $descriptions[$key] ?? $key;
            $this->line('  • ' . $queue . ' (' . $desc . ')');
        }
        $this->info('Prefetch: ' . config('rabbitmq.prefetch_count'));

        if ($timeout > 0) {
            $this->info("Timeout: {$timeout} seconds");
        } else {
            $this->info('Timeout: Unlimited (press Ctrl+C to stop)');
        }

        $this->newLine();

        try {
            $consumer->consume($timeout);

            $this->info('Consumer stopped gracefully');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Consumer failed: ' . $e->getMessage());

            Log::error('Consumer command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
