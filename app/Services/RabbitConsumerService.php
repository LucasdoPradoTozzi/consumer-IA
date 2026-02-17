<?php

namespace App\Services;

use App\DTO\JobPayload;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Channel\AMQPChannel;

class RabbitConsumerService
{
    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;
    private bool $shouldStop = false;
    private ?float $startTime = null;
    private ?int $maxRuntime = null;

    public function __construct(
        private readonly JobProcessorService $jobProcessor
    ) {}

    /**
     * Start consuming messages from RabbitMQ
     *
     * @param int $timeout Maximum runtime in seconds (0 for unlimited)
     * @return void
     * @throws \Exception
     */
    public function consume(int $timeout = 0): void
    {
        $this->startTime = microtime(true);
        $this->maxRuntime = $timeout > 0 ? $timeout : null;

        $this->connect();
        $this->setupQueues();
        $this->setupConsumers();

        Log::info('RabbitMQ consumer started', [
            'queues' => array_values(config('rabbitmq.queues')),
            'prefetch' => config('rabbitmq.prefetch_count'),
            'timeout' => $timeout > 0 ? "{$timeout}s" : 'unlimited',
        ]);

        // Register shutdown handlers
        $this->registerShutdownHandlers();

        // Start consuming
        while ($this->channel->is_consuming() && !$this->shouldStop) {
            // Check timeout if set
            if ($this->maxRuntime !== null) {
                $elapsed = microtime(true) - $this->startTime;
                if ($elapsed >= $this->maxRuntime) {
                    Log::info('Consumer timeout reached', [
                        'elapsed' => round($elapsed, 2),
                        'max_runtime' => $this->maxRuntime,
                    ]);
                    $this->shouldStop = true;
                    break;
                }
            }

            $this->channel->wait();
        }

        $totalTime = microtime(true) - $this->startTime;
        Log::info('RabbitMQ consumer stopped', [
            'runtime' => round($totalTime, 2) . 's',
            'reason' => $this->shouldStop ? 'timeout or signal' : 'no messages',
        ]);

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
            $host = config('rabbitmq.host');
            $port = config('rabbitmq.port');
            $user = config('rabbitmq.user');
            $password = config('rabbitmq.password');
            $vhost = config('rabbitmq.vhost');
            $connectionTimeout = config('rabbitmq.connection_timeout');
            $readWriteTimeout = config('rabbitmq.read_write_timeout');
            $keepalive = config('rabbitmq.keepalive');
            $heartbeat = config('rabbitmq.heartbeat');

            // Use SSL connection for port 5671 (CloudAMQP, AWS MQ, etc.)
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
                        'connection_timeout' => $connectionTimeout,
                        'read_write_timeout' => $readWriteTimeout,
                        'keepalive' => $keepalive,
                        'heartbeat' => $heartbeat,
                    ]
                );

                Log::info('Using SSL connection for RabbitMQ', ['port' => $port]);
            } else {
                // Regular connection for port 5672
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
                    connection_timeout: $connectionTimeout,
                    read_write_timeout: $readWriteTimeout,
                    context: null,
                    keepalive: $keepalive,
                    heartbeat: $heartbeat
                );

                Log::info('Using regular connection for RabbitMQ', ['port' => $port]);
            }

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
     * Routes messages to the appropriate handler based on queue name.
     * Only 3 queues are actively consumed:
     * - deduplication (process-jobs): new job intake
     * - mark-job-done: mark job as manually applied
     * - reproccess-job: reprocess with feedback
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

        // Prepare sanitized message (remove base64 images from log)
        $sanitizedBody = $this->sanitizeMessageForLog($body);

        try {
            Log::info('Routing message to handler', [
                'queue' => $queueName,
            ]);

            match ($queueName) {
                config('rabbitmq.queues.deduplication') => $this->processJobApplication($body, $queueName, $sanitizedBody),
                config('rabbitmq.queues.mark-job-done') => $this->markJobDone($body, $queueName, $sanitizedBody),
                config('rabbitmq.queues.reproccess-job') => $this->reprocessJob($body, $queueName, $sanitizedBody),
                default => throw new \InvalidArgumentException("Unknown queue: {$queueName}"),
            };

            // ACK only if successful
            $message->ack();

            Log::info('Message processed successfully and acknowledged', [
                'queue' => $queueName,
                'delivery_tag' => $deliveryTag,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process message', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'queue' => $queueName,
                'delivery_tag' => $deliveryTag,
                'body_preview' => substr($sanitizedBody, 0, 500),
            ]);

            // DO NOT ACK - let RabbitMQ re-queue the message
            throw $e;
        }
    }


    /**
     * Sanitize message for storage (remove base64 images)
     *
     * @param string $body
     * @return string
     */
    private function sanitizeMessageForLog(string $body): string
    {
        $data = json_decode($body, true);

        if ($data) {
            // Check if images is in root level
            if (isset($data['images'])) {
                $hasImages = !empty($data['images']);
                $data['images'] = $hasImages ? 'hasImages' : 'noImage';
                return json_encode($data, JSON_UNESCAPED_SLASHES);
            }

            // Check if images is inside data object
            if (isset($data['data']['images'])) {
                $hasImages = !empty($data['data']['images']);
                $data['data']['images'] = $hasImages ? 'hasImages' : 'noImage';
                return json_encode($data, JSON_UNESCAPED_SLASHES);
            }
        }

        return $body;
    }

    /**
     * Process job application from deduplication queue.
     * Expects full JobPayload format (with job/candidate data).
     *
     * @param string $body
     * @param string $queueName
     * @param string $sanitizedBody
     * @return void
     */
    private function processJobApplication(string $body, string $queueName, string $sanitizedBody): void
    {
        Log::info('[RabbitConsumerService] Processing job application', [
            'queue' => $queueName,
            'body_preview' => substr($sanitizedBody, 0, 200),
        ]);

        try {
            // Transform message if it's in simple format (link, email, optional job_info/images)
            $body = $this->transformLegacyMessageFormat($body);

            $payload = JobPayload::fromJson($body);

            Log::info('[RabbitConsumerService] Payload parsed successfully', [
                'type' => $payload->type,
                'has_callback_url' => !empty($payload->callbackUrl),
                'priority' => $payload->priority,
            ]);

            $this->jobProcessor->process($payload, $queueName, $sanitizedBody);

            Log::info('[RabbitConsumerService] Processing completed successfully');
        } catch (\InvalidArgumentException $e) {
            Log::error('[RabbitConsumerService] Invalid message format', [
                'error' => $e->getMessage(),
                'body_preview' => substr($sanitizedBody, 0, 500),
            ]);
            throw $e;
        }
    }


    /**
     * Transform legacy message format to expected JobPayload format
     * 
     * Legacy format: {link, email, job_info, images}
     * Expected format: {job_id, type, data: {job, candidate, images}}
     */
    private function transformLegacyMessageFormat(string $body): string
    {
        $data = json_decode($body, true);

        if (!$data) {
            return $body;
        }

        // Check if it's already in the correct format
        if (isset($data['job_id']) && isset($data['type']) && isset($data['data'])) {
            Log::info('[process-jobs] Message already in expected format');
            return $body;
        }

        // Check if it's in simple format (has link and email at minimum)
        if (isset($data['link']) && isset($data['email'])) {
            Log::info('[process-jobs] Transforming simple message format...', [
                'has_link' => true,
                'has_email' => true,
                'has_job_info' => isset($data['job_info']),
                'has_images' => isset($data['images']),
            ]);

            // Extract image if it's a valid base64 string
            $imageBase64 = null;
            if (isset($data['images'])) {
                if (is_array($data['images']) && !empty($data['images'])) {
                    // Get first image from array - ensure it's a string
                    $firstImage = $data['images'][0];
                    if (is_string($firstImage) && strlen($firstImage) > 100) {
                        $imageBase64 = $firstImage;
                    }
                } elseif (is_string($data['images']) && strlen($data['images']) > 100 && $data['images'] !== 'hasImages') {
                    // Only consider it an image if it's a reasonably long string (base64 images are long)
                    $imageBase64 = $data['images'];
                }
            }

            // Transform to expected format WITHOUT job_id
            $transformed = [
                'type' => 'job_application',
                'data' => [
                    'job' => [
                        'title' => $data['job_title'] ?? 'Job Application',
                        'company' => $data['company'] ?? 'Unknown Company',
                        'description' => $data['job_info'] ?? '',
                        'link' => $data['link'],
                    ],
                    'candidate' => [
                        'email' => $data['email'],
                        'name' => config('candidate.name', 'Candidate'),
                    ],
                ],
            ];

            // Add image only if valid base64 detected
            if ($imageBase64) {
                $transformed['data']['image'] = $imageBase64;
            }

            Log::info('[process-jobs] Message transformed', [
                'original_link' => $data['link'],
                'has_valid_image' => !empty($imageBase64),
                'image_length' => $imageBase64 ? strlen($imageBase64) : 0,
            ]);

            return json_encode($transformed);
        }

        // If format is unknown, log and return original
        Log::warning('[process-jobs] Unknown message format', [
            'keys' => array_keys($data),
        ]);

        return $body;
    }
    /**
     * Mark job as done (already applied)
     *
     * @param string $body
     * @param string $queueName
     * @param string $sanitizedBody
     * @return void
     */
    private function markJobDone(string $body, string $queueName, string $sanitizedBody): void
    {
        Log::info('[RabbitConsumerService] Entrou em markJobDone', [
            'queue' => $queueName,
            'body_preview' => substr($sanitizedBody, 0, 200),
        ]);
        Log::info('[mark-job-done] Parsing message');

        $data = json_decode($body, true);

        if (!isset($data['id'])) {
            Log::error('[mark-job-done] Invalid message format', [
                'error' => 'Missing "id" field',
                'body_preview' => substr($sanitizedBody, 0, 200),
            ]);
            throw new \InvalidArgumentException('Missing "id" field in mark-job-done message');
        }

        $jobId = $data['id'];

        Log::info('[mark-job-done] Marking job as manually completed', [
            'job_id' => $jobId,
            'reason' => 'User manually marked as applied',
        ]);

        $this->jobProcessor->markJobAsDone($jobId, $queueName, $sanitizedBody);

        Log::info('[mark-job-done] Job marked successfully', [
            'job_id' => $jobId,
        ]);
    }

    /**
     * Reprocess job with additional message/feedback
     *
     * @param string $body
     * @param string $queueName
     * @param string $sanitizedBody
     * @return void
     */
    private function reprocessJob(string $body, string $queueName, string $sanitizedBody): void

    {
        Log::info('[RabbitConsumerService] Entrou em reprocessJob', [
            'queue' => $queueName,
            'body_preview' => substr($sanitizedBody, 0, 200),
        ]);
        Log::info('[reproccess-job] Parsing message');

        $data = json_decode($body, true);

        if (!isset($data['id'])) {
            Log::error('[reproccess-job] Invalid message format', [
                'error' => 'Missing "id" field',
                'body_preview' => substr($sanitizedBody, 0, 200),
            ]);
            throw new \InvalidArgumentException('Missing "id" field in reproccess-job message');
        }

        if (!isset($data['message'])) {
            Log::error('[reproccess-job] Invalid message format', [
                'error' => 'Missing "message" field',
                'job_id' => $data['id'],
            ]);
            throw new \InvalidArgumentException('Missing "message" field in reproccess-job message');
        }

        $jobId = $data['id'];
        $message = $data['message'];

        Log::info('[reproccess-job] Re-classifying job with additional context', [
            'job_id' => $jobId,
            'message_preview' => substr($message, 0, 100),
            'full_message_length' => strlen($message),
            'reason' => 'User provided additional feedback for re-classification',
        ]);

        $this->jobProcessor->reprocessJob($jobId, $message, $queueName, $sanitizedBody);

        Log::info('[reproccess-job] Job reprocessed successfully', [
            'job_id' => $jobId,
        ]);
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
