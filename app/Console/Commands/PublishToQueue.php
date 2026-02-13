<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class PublishToQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:publish
                            {queue : Queue name (process-jobs, mark-job-done, reproccess-job)}
                            {--id= : Job ID (required for mark-job-done and reproccess-job)}
                            {--message= : Message for reproccess-job}
                            {--job-title= : Job title for process-jobs}
                            {--company= : Company name for process-jobs}
                            {--description= : Job description for process-jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish a message to a RabbitMQ queue';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $queueKey = $this->argument('queue');

        // Map queue names
        $queueMap = [
            'process-jobs' => config('rabbitmq.queues.process-jobs'),
            'mark-job-done' => config('rabbitmq.queues.mark-job-done'),
            'reproccess-job' => config('rabbitmq.queues.reproccess-job'),
        ];

        if (!isset($queueMap[$queueKey])) {
            $this->error("Invalid queue: {$queueKey}");
            $this->info('Valid queues: process-jobs, mark-job-done, reproccess-job');
            return Command::FAILURE;
        }

        $queueName = $queueMap[$queueKey];

        try {
            // Build payload based on queue
            $payload = match ($queueKey) {
                'process-jobs' => $this->buildProcessJobsPayload(),
                'mark-job-done' => $this->buildMarkJobDonePayload(),
                'reproccess-job' => $this->buildReprocessJobPayload(),
            };

            // Connect to RabbitMQ
            $connection = new AMQPStreamConnection(
                config('rabbitmq.host'),
                config('rabbitmq.port'),
                config('rabbitmq.user'),
                config('rabbitmq.password'),
                config('rabbitmq.vhost')
            );

            $channel = $connection->channel();

            // Declare queue
            $channel->queue_declare(
                $queueName,
                false,
                true, // durable
                false,
                false
            );

            // Publish message
            $message = new AMQPMessage(
                json_encode($payload),
                ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
            );

            $channel->basic_publish($message, '', $queueName);

            $this->info("✓ Message published to queue: {$queueName}");
            $this->line(json_encode($payload, JSON_PRETTY_PRINT));

            // Close
            $channel->close();
            $connection->close();

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to publish message: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Build payload for process-jobs queue
     */
    private function buildProcessJobsPayload(): array
    {
        $jobId = $this->option('id') ?? 'test-job-' . time();
        $jobTitle = $this->option('job-title') ?? 'Senior Laravel Developer';
        $company = $this->option('company') ?? 'TechCorp Brasil';
        $description = $this->option('description') ?? 'Buscamos desenvolvedor Laravel sênior com experiência em microservices, Docker e RabbitMQ.';

        return [
            'jobId' => $jobId,
            'type' => 'job_application',
            'data' => [
                'job' => [
                    'title' => $jobTitle,
                    'company' => $company,
                    'description' => $description,
                    'required_skills' => ['PHP', 'Laravel', 'Docker', 'PostgreSQL', 'RabbitMQ'],
                    'salary' => 'R$ 12.000 - R$ 18.000',
                    'location' => 'São Paulo - Remoto',
                ],
                'candidate' => [
                    'name' => config('candidate.name') ?? 'Test User',
                    'email' => config('candidate.email') ?? 'test@example.com',
                ],
            ],
            'metadata' => [
                'source' => 'artisan_command',
                'published_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Build payload for mark-job-done queue
     */
    private function buildMarkJobDonePayload(): array
    {
        $jobId = $this->option('id');

        if (!$jobId) {
            throw new \InvalidArgumentException('--id is required for mark-job-done');
        }

        return [
            'id' => $jobId,
        ];
    }

    /**
     * Build payload for reproccess-job queue
     */
    private function buildReprocessJobPayload(): array
    {
        $jobId = $this->option('id');
        $message = $this->option('message');

        if (!$jobId) {
            throw new \InvalidArgumentException('--id is required for reproccess-job');
        }

        if (!$message) {
            throw new \InvalidArgumentException('--message is required for reproccess-job');
        }

        return [
            'id' => $jobId,
            'message' => $message,
        ];
    }
}
