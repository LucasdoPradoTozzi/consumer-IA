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
        $this->setupQueue();
        $this->setupConsumer();

        Log::info('RabbitMQ consumer started', [
            'queue' => config('rabbitmq.queue'),
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
     * Setup queue and prefetch
     *
     * @return void
     */
    private function setupQueue(): void
    {
        $queue = config('rabbitmq.queue');

        // Declare queue (passive=false, durable=true, exclusive=false, auto_delete=false)
        $this->channel->queue_declare(
            queue: $queue,
            passive: false,
            durable: true,
            exclusive: false,
            auto_delete: false
        );

        // Set prefetch count to 1 (no parallelism)
        $this->channel->basic_qos(
            prefetch_size: 0,
            prefetch_count: config('rabbitmq.prefetch_count'),
            a_global: false
        );

        Log::info('Queue configured', [
            'queue' => $queue,
            'prefetch_count' => config('rabbitmq.prefetch_count'),
        ]);
    }

    /**
     * Setup consumer callback
     *
     * @return void
     */
    private function setupConsumer(): void
    {
        $queue = config('rabbitmq.queue');
        $consumerTag = config('rabbitmq.consumer_tag');

        $callback = function (AMQPMessage $message) {
            $this->handleMessage($message);
        };

        $this->channel->basic_consume(
            queue: $queue,
            consumer_tag: $consumerTag,
            no_local: false,
            no_ack: false, // Manual ACK
            exclusive: false,
            nowait: false,
            callback: $callback
        );

        Log::info('Consumer registered', [
            'consumer_tag' => $consumerTag,
            'manual_ack' => true,
        ]);
    }

    /**
     * Handle incoming message
     *
     * @param AMQPMessage $message
     * @return void
     */
    private function handleMessage(AMQPMessage $message): void
    {
        $body = $message->getBody();
        $deliveryTag = $message->getDeliveryTag();

        Log::info('Message received', [
            'delivery_tag' => $deliveryTag,
            'body_size' => strlen($body),
        ]);

        try {
            // Parse payload
            $payload = JobPayload::fromJson($body);

            Log::info('Payload parsed', [
                'job_id' => $payload->jobId,
                'type' => $payload->type,
                'delivery_tag' => $deliveryTag,
            ]);

            // Process job
            $this->jobProcessor->process($payload);

            // ACK only if successful
            $message->ack();

            Log::info('Message acknowledged', [
                'job_id' => $payload->jobId,
                'delivery_tag' => $deliveryTag,
            ]);
        } catch (\Exception $e) {
            // Log error with full context
            Log::error('Failed to process message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'delivery_tag' => $deliveryTag,
                'body' => $body,
            ]);

            // DO NOT ACK - let RabbitMQ re-queue the message
            // Re-throw exception to stop consumer if needed
            throw $e;
        }
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
