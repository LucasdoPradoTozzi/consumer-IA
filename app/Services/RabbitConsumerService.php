<?php

namespace App\Services;

use App\DTO\JobPayload;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Channel\AMQPChannel;

class RabbitConsumerService
{
    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;
    private bool $shouldStop = false;

    public function __construct(
        private readonly JobProcessorService $jobProcessor
    ) {}

    /**
     * Start consuming messages from RabbitMQ
     *
     * @return void
     * @throws \Exception
     */
    public function consume(): void
    {
        $this->connect();
        $this->setupQueues();
        $this->setupConsumers();

        Log::info('RabbitMQ consumer started', [
            'queues' => array_values(config('rabbitmq.queues')),
            'prefetch' => config('rabbitmq.prefetch_count'),
        ]);

        // Register shutdown handlers
        $this->registerShutdownHandlers();

        // Start consuming
        while ($this->channel->is_consuming() && !$this->shouldStop) {
            $this->channel->wait();
        }

        $this->disconnect();
    }

    /**
     * Connect to RabbitMQ
     *
     * @return void
     * @throws \Exception
     */
    private function connect(): void
    {
        try {
            $this->connection = new AMQPStreamConnection(
                host: config('rabbitmq.host'),
                port: config('rabbitmq.port'),
                user: config('rabbitmq.user'),
                password: config('rabbitmq.password'),
                vhost: config('rabbitmq.vhost'),
                insist: false,
                login_method: 'AMQPLAIN',
                login_response: null,
                locale: 'en_US',
                connection_timeout: config('rabbitmq.connection_timeout'),
                read_write_timeout: config('rabbitmq.read_write_timeout'),
                context: null,
                keepalive: config('rabbitmq.keepalive'),
                heartbeat: config('rabbitmq.heartbeat')
            );

            $this->channel = $this->connection->channel();

            Log::info('Connected to RabbitMQ', [
                'host' => config('rabbitmq.host'),
                'port' => config('rabbitmq.port'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to connect to RabbitMQ', [
                'error' => $e->getMessage(),
                'host' => config('rabbitmq.host'),
                'port' => config('rabbitmq.port'),
            ]);
            throw $e;
        }
    }

    /**
     * Setup all queues and prefetch
     *
     * @return void
     */
    private function setupQueues(): void
    {
        $queues = config('rabbitmq.queues');

        foreach ($queues as $queueName) {
            // Declare queue (passive=false, durable=true, exclusive=false, auto_delete=false)
            $this->channel->queue_declare(
                queue: $queueName,
                passive: false,
                durable: true,
                exclusive: false,
                auto_delete: false
            );

            Log::info('Queue declared', ['queue' => $queueName]);
        }

        // Set prefetch count to 1 (no parallelism)
        $this->channel->basic_qos(
            prefetch_size: 0,
            prefetch_count: config('rabbitmq.prefetch_count'),
            a_global: false
        );

        Log::info('Queues configured', [
            'queues' => array_values($queues),
            'prefetch_count' => config('rabbitmq.prefetch_count'),
        ]);
    }

    /**
     * Setup consumer callbacks for all queues
     *
     * @return void
     */
    private function setupConsumers(): void
    {
        $queues = config('rabbitmq.queues');
        $consumerTagBase = config('rabbitmq.consumer_tag');

        foreach ($queues as $queueKey => $queueName) {
            $consumerTag = "{$consumerTagBase}_{$queueKey}";

            $callback = function (AMQPMessage $message) use ($queueName) {
                $this->handleMessage($message, $queueName);
            };

            $this->channel->basic_consume(
                queue: $queueName,
                consumer_tag: $consumerTag,
                no_local: false,
                no_ack: false, // Manual ACK
                exclusive: false,
                nowait: false,
                callback: $callback
            );

            Log::info('Consumer registered', [
                'queue' => $queueName,
                'consumer_tag' => $consumerTag,
                'manual_ack' => true,
            ]);
        }
    }

    /**
     * Handle incoming message
     *
     * @param AMQPMessage $message
     * @param string $queueName
     * @return void
     */
    private function handleMessage(AMQPMessage $message, string $queueName): void
    {
        $body = $message->getBody();
        $deliveryTag = $message->getDeliveryTag();

        Log::info('Message received', [
            'queue' => $queueName,
            'delivery_tag' => $deliveryTag,
            'body_size' => strlen($body),
        ]);

        try {
            // Process based on queue
            match ($queueName) {
                config('rabbitmq.queues.process-jobs') => $this->processJobApplication($body),
                config('rabbitmq.queues.mark-job-done') => $this->markJobDone($body),
                config('rabbitmq.queues.reproccess-job') => $this->reprocessJob($body),
                default => throw new \InvalidArgumentException("Unknown queue: {$queueName}"),
            };

            // ACK only if successful
            $message->ack();

            Log::info('Message acknowledged', [
                'queue' => $queueName,
                'delivery_tag' => $deliveryTag,
            ]);
        } catch (\Exception $e) {
            // Log error with full context
            Log::error('Failed to process message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'queue' => $queueName,
                'delivery_tag' => $deliveryTag,
                'body' => $body,
            ]);

            // DO NOT ACK - let RabbitMQ re-queue the message
            // Re-throw exception to stop consumer if needed
            throw $e;
        }
    }

    /**
     * Process job application (full 7-stage pipeline)
     *
     * @param string $body
     * @return void
     */
    private function processJobApplication(string $body): void
    {
        $payload = JobPayload::fromJson($body);

        Log::info('[process-jobs] Processing job application', [
            'job_id' => $payload->jobId,
            'type' => $payload->type,
        ]);

        $this->jobProcessor->process($payload);
    }

    /**
     * Mark job as done (already applied)
     *
     * @param string $body
     * @return void
     */
    private function markJobDone(string $body): void
    {
        $data = json_decode($body, true);

        if (!isset($data['id'])) {
            throw new \InvalidArgumentException('Missing "id" field in mark-job-done message');
        }

        $jobId = $data['id'];

        Log::info('[mark-job-done] Marking job as done', [
            'job_id' => $jobId,
        ]);

        $this->jobProcessor->markJobAsDone($jobId);
    }

    /**
     * Reprocess job with additional message/feedback
     *
     * @param string $body
     * @return void
     */
    private function reprocessJob(string $body): void
    {
        $data = json_decode($body, true);

        if (!isset($data['id'])) {
            throw new \InvalidArgumentException('Missing "id" field in reproccess-job message');
        }

        if (!isset($data['message'])) {
            throw new \InvalidArgumentException('Missing "message" field in reproccess-job message');
        }

        $jobId = $data['id'];
        $message = $data['message'];

        Log::info('[reproccess-job] Reprocessing job', [
            'job_id' => $jobId,
            'message_length' => strlen($message),
        ]);

        $this->jobProcessor->reprocessJob($jobId, $message);
    }

    /**
     * Register shutdown handlers for graceful shutdown
     *
     * @return void
     */
    private function registerShutdownHandlers(): void
    {
        pcntl_signal(SIGTERM, function () {
            Log::info('SIGTERM received, shutting down gracefully...');
            $this->shouldStop = true;
        });

        pcntl_signal(SIGINT, function () {
            Log::info('SIGINT received, shutting down gracefully...');
            $this->shouldStop = true;
        });

        pcntl_async_signals(true);
    }

    /**
     * Disconnect from RabbitMQ
     *
     * @return void
     */
    private function disconnect(): void
    {
        try {
            if ($this->channel) {
                $this->channel->close();
            }

            if ($this->connection) {
                $this->connection->close();
            }

            Log::info('Disconnected from RabbitMQ');
        } catch (\Exception $e) {
            Log::error('Error during disconnect', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
