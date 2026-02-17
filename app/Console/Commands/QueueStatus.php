<?php

namespace App\Console\Commands;

use App\Models\JobApplication;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Connection\AMQPSSLConnection;

class QueueStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show RabbitMQ queues status and last processed messages';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('RabbitMQ Queue Status');
        $this->newLine();

        // Get queue counts from RabbitMQ
        $queueCounts = $this->getQueueCounts();

        if ($queueCounts === false) {
            $this->error('Failed to connect to RabbitMQ');
            return Command::FAILURE;
        }

        // Display queue status
        $this->displayQueueStatus($queueCounts);

        $this->newLine();
        $this->info('Last Messages (from Redis - real-time debug)');
        $this->newLine();

        // Display last messages from Redis
        $this->displayLastMessages();

        $this->newLine();
        $this->info('Overall Statistics (from database)');
        $this->newLine();

        // Display statistics
        $this->displayStatistics();

        return Command::SUCCESS;
    }

    /**
     * Get message count from each queue
     */
    private function getQueueCounts(): array|false
    {
        try {
            $host = config('rabbitmq.host');
            $port = config('rabbitmq.port');
            $user = config('rabbitmq.user');
            $password = config('rabbitmq.password');
            $vhost = config('rabbitmq.vhost');

            // Create connection based on port
            if ($port == 5671 || $port == '5671') {
                $sslOptions = [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ];

                $connection = new AMQPSSLConnection(
                    host: $host,
                    port: $port,
                    user: $user,
                    password: $password,
                    vhost: $vhost,
                    ssl_options: $sslOptions,
                );
            } else {
                $connection = new AMQPStreamConnection(
                    host: $host,
                    port: $port,
                    user: $user,
                    password: $password,
                    vhost: $vhost,
                );
            }

            $channel = $connection->channel();

            $queues = config('rabbitmq.queues');
            $counts = [];

            foreach ($queues as $queueKey => $queueName) {
                // Use passive=true to just get info without declaring
                [$queueName, $messageCount, $consumerCount] = $channel->queue_declare(
                    queue: $queueName,
                    passive: true
                );

                $counts[$queueKey] = [
                    'name' => $queueName,
                    'messages' => $messageCount,
                    'consumers' => $consumerCount,
                ];
            }

            $channel->close();
            $connection->close();

            return $counts;
        } catch (\Exception $e) {
            $this->error('Error connecting to RabbitMQ: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Display queue status table
     */
    private function displayQueueStatus(array $queueCounts): void
    {
        $rows = [];

        foreach ($queueCounts as $queueKey => $data) {
            $rows[] = [
                ucfirst(str_replace('-', ' ', $queueKey)),
                $data['name'],
                $data['messages'] > 0 ? "<fg=yellow>{$data['messages']}</>" : "<fg=green>0</>",
                $data['consumers'] > 0 ? "<fg=green>{$data['consumers']}</>" : "<fg=red>0</>",
            ];
        }

        $this->table(
            ['Queue', 'Name', 'Messages', 'Consumers'],
            $rows
        );
    }

    /**
     * Display last messages from Redis
     */
    private function displayLastMessages(): void
    {
        $queues = config('rabbitmq.queues');

        foreach ($queues as $queueKey => $queueName) {
            $redisKey = "last_message_{$queueName}";

            $message = Redis::get($redisKey);
            $status = Redis::get("{$redisKey}_status");
            $timestamp = Redis::get("{$redisKey}_timestamp");
            $size = Redis::get("{$redisKey}_size");
            $error = Redis::get("{$redisKey}_error");

            $this->line("<fg=cyan>━━━ {$queueName} ━━━</>");

            if ($message) {
                // Status color
                $statusColor = match ($status) {
                    'success' => 'green',
                    'processing' => 'yellow',
                    'failed' => 'red',
                    default => 'gray',
                };

                $statusIcon = match ($status) {
                    'success' => '✓',
                    'processing' => '⏳',
                    'failed' => '✗',
                    default => '?',
                };

                $this->line("  <fg={$statusColor}>{$statusIcon}</> Status: <fg={$statusColor}>{$status}</>");

                if ($timestamp) {
                    $time = \Carbon\Carbon::parse($timestamp);
                    $this->line("  Time: <fg=yellow>{$time->diffForHumans()}</> ({$time->format('Y-m-d H:i:s')})");
                }

                if ($size) {
                    $this->line("  Size: " . number_format($size / 1024, 2) . " KB");
                }

                if ($error) {
                    $this->line("  <fg=red>Error: {$error}</>");
                }

                // Parse and display message
                $messageData = json_decode($message, true);
                if ($messageData) {
                    // Show key fields
                    if (isset($messageData['job_id'])) {
                        $this->line("  Job ID: {$messageData['job_id']}");
                    }

                    if (isset($messageData['type'])) {
                        $this->line("  Type: {$messageData['type']}");
                    }

                    if (isset($messageData['data']['job']['title'])) {
                        $this->line("  Title: {$messageData['data']['job']['title']}");
                    }

                    if (isset($messageData['data']['job']['company'])) {
                        $this->line("  Company: {$messageData['data']['job']['company']}");
                    }

                    // Show full message preview
                    $this->newLine();
                    $this->line("  <fg=gray>Message preview:</>");
                    $json = json_encode($messageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    $lines = explode("\n", $json);
                    $preview = array_slice($lines, 0, 20); // First 20 lines

                    foreach ($preview as $line) {
                        $this->line("  <fg=gray>{$line}</>");
                    }

                    if (count($lines) > 20) {
                        $remaining = count($lines) - 20;
                        $this->line("  <fg=gray>... ({$remaining} more lines)</>");
                    }
                } else {
                    $this->line("  <fg=gray>Raw: " . substr($message, 0, 200) . "...</>");
                }
            } else {
                $this->line("  <fg=gray>No messages received yet</>");
            }

            $this->newLine();
        }
    }

    /**
     * Display overall statistics from database
     */
    private function displayStatistics(): void
    {
        $totalProcessed = JobApplication::count();
        $totalCompleted = JobApplication::where('status', 'completed')->count();
        $totalRejected = JobApplication::where('status', 'rejected')->count();
        $totalFailed = JobApplication::where('status', 'failed')->count();
        $avgScore = JobApplication::whereNotNull('match_score')->avg('match_score');
        $avgProcessingTime = JobApplication::whereNotNull('processing_time_seconds')->avg('processing_time_seconds');

        $this->line("  Total processed: <fg=yellow>{$totalProcessed}</>");
        $this->line("  Completed: <fg=green>{$totalCompleted}</>");
        $this->line("  Rejected: <fg=red>{$totalRejected}</>");
        $this->line("  Failed: <fg=red>{$totalFailed}</>");

        if ($avgScore !== null) {
            $this->line("  Average score: <fg=yellow>" . number_format($avgScore, 1) . "/100</>");
        }

        if ($avgProcessingTime !== null) {
            $this->line("  Average processing time: <fg=yellow>" . number_format($avgProcessingTime, 1) . "s</>");
        }

        // Top 5 most recent jobs
        $this->newLine();
        $this->line("<fg=cyan>━━━ Recent Jobs (Database) ━━━</>");

        $recentJobs = JobApplication::orderBy('updated_at', 'desc')
            ->take(5)
            ->get(['job_id', 'job_title', 'job_company', 'status', 'match_score', 'updated_at']);

        if ($recentJobs->count() > 0) {
            foreach ($recentJobs as $job) {
                $statusColor = $this->getStatusColor($job->status);
                $scoreText = $job->match_score ? " [{$job->match_score}/100]" : "";
                $this->line("  <fg={$statusColor}>●</> {$job->job_title} @ {$job->job_company}{$scoreText}");
                $this->line("     ID: {$job->job_id} | Status: {$job->status} | {$job->updated_at->diffForHumans()}");
            }
        } else {
            $this->line("  <fg=gray>No jobs in database yet</>");
        }
    }

    /**
     * Display last processed messages from database (OLD METHOD - KEPT FOR REFERENCE)
     */
    private function displayLastProcessedMessages(): void
    {
        $queues = config('rabbitmq.queues');

        foreach ($queues as $queueKey => $queueName) {
            $this->line("<fg=cyan>━━━ {$queueName} ━━━</>");

            $lastMessage = JobApplication::where('queue_name', $queueName)
                ->latest('updated_at')
                ->first();

            if ($lastMessage) {
                $this->line("  <fg=green>✓</> Last processed: <fg=yellow>{$lastMessage->updated_at->diffForHumans()}</>");
                $this->line("  Job ID: {$lastMessage->job_id}");
                $this->line("  Status: <fg=" . $this->getStatusColor($lastMessage->status) . ">{$lastMessage->status}</>");

                if ($lastMessage->job_title) {
                    $this->line("  Title: {$lastMessage->job_title}");
                }

                if ($lastMessage->job_company) {
                    $this->line("  Company: {$lastMessage->job_company}");
                }

                if ($lastMessage->match_score !== null) {
                    $color = $lastMessage->match_score >= 70 ? 'green' : 'yellow';
                    $this->line("  Score: <fg={$color}>{$lastMessage->match_score}/100</>");
                }

                // Show raw message preview (without base64)
                if ($lastMessage->raw_message) {
                    $message = json_decode($lastMessage->raw_message, true);
                    if ($message) {
                        // Remove base64 image if present
                        if (isset($message['data']['image'])) {
                            $imageSize = strlen($message['data']['image']);
                            $message['data']['image'] = "[BASE64 IMAGE: " . number_format($imageSize / 1024, 2) . " KB]";
                        }

                        $this->line("  Message preview:");
                        $this->line("  " . json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    }
                }
            } else {
                $this->line("  <fg=gray>No messages processed yet</>");
            }

            $this->newLine();
        }

        // Overall statistics
        $this->line("<fg=cyan>━━━ Overall Statistics ━━━</>");
        $totalProcessed = JobApplication::count();
        $totalCompleted = JobApplication::where('status', 'completed')->count();
        $totalRejected = JobApplication::where('status', 'rejected')->count();
        $totalFailed = JobApplication::where('status', 'failed')->count();
        $avgScore = JobApplication::whereNotNull('match_score')->avg('match_score');
        $avgProcessingTime = JobApplication::whereNotNull('processing_time_seconds')->avg('processing_time_seconds');

        $this->line("  Total processed: <fg=yellow>{$totalProcessed}</>");
        $this->line("  Completed: <fg=green>{$totalCompleted}</>");
        $this->line("  Rejected: <fg=red>{$totalRejected}</>");
        $this->line("  Failed: <fg=red>{$totalFailed}</>");

        if ($avgScore !== null) {
            $this->line("  Average score: <fg=yellow>" . number_format($avgScore, 1) . "/100</>");
        }

        if ($avgProcessingTime !== null) {
            $this->line("  Average processing time: <fg=yellow>" . number_format($avgProcessingTime, 1) . "s</>");
        }
    }

    /**
     * Get color for status
     */
    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'completed' => 'green',
            'processing', 'classified', 'scored' => 'yellow',
            'rejected', 'failed' => 'red',
            default => 'gray',
        };
    }
}
