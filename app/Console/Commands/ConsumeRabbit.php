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
                            {--once : Process only one message and exit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consume messages from RabbitMQ queues (3 queues)';

    /**
     * Execute the console command.
     *
     * @param RabbitConsumerService $consumer
     * @return int
     */
    public function handle(RabbitConsumerService $consumer): int
    {
        $queues = config('rabbitmq.queues');

        $this->info('Starting RabbitMQ consumer...');
        $this->info('Host: ' . config('rabbitmq.host'));
        $this->info('Queues:');
        $this->line('  • ' . $queues['process-jobs'] . ' (full processing)');
        $this->line('  • ' . $queues['mark-job-done'] . ' (mark as applied)');
        $this->line('  • ' . $queues['reproccess-job'] . ' (reprocess with message)');
        $this->info('Prefetch: ' . config('rabbitmq.prefetch_count'));
        $this->info('Press Ctrl+C to stop');
        $this->newLine();

        try {
            $consumer->consume();

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
