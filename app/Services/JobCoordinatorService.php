<?php

namespace App\Services;

use App\DTO\JobPayload;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Message\AMQPMessage;

class JobCoordinatorService
{
    private ?AMQPStreamConnection $connection = null;
    private $channel = null;

    public function __construct() {}

    /**
     * Send job to deduplication queue
     */
    public function sendToDeduplication(JobPayload $payload): void
    {
        $this->sendMessage($payload, config('rabbitmq.queues.deduplication'));
    }

    /**
     * Send job to extraction queue
     */
    public function sendToExtraction(string $jobId): void
    {
        $message = json_encode(['job_id' => $jobId]);
        $this->sendRawMessage($message, config('rabbitmq.queues.extraction'));
    }

    /**
     * Send job to scoring queue
     */
    public function sendToScoring(string $jobId): void
    {
        $message = json_encode(['job_id' => $jobId]);
        $this->sendRawMessage($message, config('rabbitmq.queues.scoring'));
    }

    /**
     * Send job to generation queue
     */
    public function sendToGeneration(string $jobId): void
    {
        $message = json_encode(['job_id' => $jobId]);
        $this->sendRawMessage($message, config('rabbitmq.queues.generation'));
    }

    /**
     * Send job to email queue
     */
    public function sendToEmail(string $jobId): void
    {
        $message = json_encode(['job_id' => $jobId]);
        $this->sendRawMessage($message, config('rabbitmq.queues.email'));
    }

    /**
     * Send message to queue
     */
    private function sendMessage(JobPayload $payload, string $queueName): void
    {
        try {
            $this->ensureConnection();

            $messageBody = json_encode($payload->toArray());
            $message = new AMQPMessage($messageBody, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type' => 'application/json',
            ]);

            $this->channel->basic_publish($message, '', $queueName);

            Log::info('[JobCoordinator] Message sent to queue', [
                'queue' => $queueName,
                'job_id' => $payload->jobId,
            ]);
        } catch (\Exception $e) {
            Log::error('[JobCoordinator] Failed to send message', [
                'queue' => $queueName,
                'job_id' => $payload->jobId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Send raw message to queue
     */
    private function sendRawMessage(string $message, string $queueName): void
    {
        try {
            $this->ensureConnection();

            $amqpMessage = new AMQPMessage($message, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type' => 'application/json',
            ]);

            $this->channel->basic_publish($amqpMessage, '', $queueName);

            Log::info('[JobCoordinator] Raw message sent to queue', [
                'queue' => $queueName,
                'message_size' => strlen($message),
            ]);
        } catch (\Exception $e) {
            Log::error('[JobCoordinator] Failed to send raw message', [
                'queue' => $queueName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Ensure RabbitMQ connection is established
     */
    private function ensureConnection(): void
    {
        if ($this->connection && $this->connection->isConnected()) {
            return;
        }

        $host = config('rabbitmq.host');
        $port = config('rabbitmq.port');
        $user = config('rabbitmq.user');
        $password = config('rabbitmq.password');
        $vhost = config('rabbitmq.vhost');

        if ($port == 5671 || $port == '5671') {
            $sslOptions = [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ];

            $this->connection = new AMQPSSLConnection(
                host: $host,
                port: $port,
                user: $user,
                password: $password,
                vhost: $vhost,
                ssl_options: $sslOptions,
                options: [
                    'connection_timeout' => config('rabbitmq.connection_timeout'),
                    'read_write_timeout' => config('rabbitmq.read_write_timeout'),
                    'keepalive' => config('rabbitmq.keepalive'),
                    'heartbeat' => config('rabbitmq.heartbeat'),
                ]
            );
        } else {
            $this->connection = new AMQPStreamConnection(
                host: $host,
                port: $port,
                user: $user,
                password: $password,
                vhost: $vhost,
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
        }

        $this->channel = $this->connection->channel();

        Log::info('[JobCoordinator] Connected to RabbitMQ');
    }

    /**
     * Close connection
     */
    public function __destruct()
    {
        if ($this->channel) {
            $this->channel->close();
        }
        if ($this->connection) {
            $this->connection->close();
        }
    }
}
