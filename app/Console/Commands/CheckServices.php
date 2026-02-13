<?php

namespace App\Console\Commands;

use App\Services\OllamaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class CheckServices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'worker:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check if all required services are available';

    /**
     * Execute the console command.
     *
     * @param OllamaService $ollama
     * @return int
     */
    public function handle(OllamaService $ollama): int
    {
        $this->info('Checking service availability...');
        $this->newLine();

        $allOk = true;

        // Check RabbitMQ
        $this->info('Checking RabbitMQ...');
        try {
            $connection = new AMQPStreamConnection(
                config('rabbitmq.host'),
                config('rabbitmq.port'),
                config('rabbitmq.user'),
                config('rabbitmq.password'),
                config('rabbitmq.vhost'),
                false,
                'AMQPLAIN',
                null,
                'en_US',
                5.0,
                5.0
            );
            $connection->close();
            $this->line('✓ RabbitMQ: <fg=green>Available</> (' . config('rabbitmq.host') . ':' . config('rabbitmq.port') . ')');
        } catch (\Exception $e) {
            $this->line('✗ RabbitMQ: <fg=red>Unavailable</> - ' . $e->getMessage());
            $allOk = false;
        }

        // Check Ollama
        $this->info('Checking Ollama...');
        if ($ollama->isAvailable()) {
            $models = $ollama->getAvailableModels();
            $profile = config('ollama.profile');
            $profiles = config('ollama.profiles');
            $requiredModel = isset($profiles[$profile]['model']) ? $profiles[$profile]['model'] : null;

            $this->line('✓ Ollama: <fg=green>Available</> (' . config('ollama.url') . ')');
            $description = isset($profiles[$profile]['description']) ? $profiles[$profile]['description'] : 'N/A';
            $this->line("  Active profile: <fg=cyan>{$profile}</> ({$description})");;

            if (!empty($models)) {
                $this->line('  Models installed: ' . count($models));
                foreach ($models as $model) {
                    $name = isset($model['name']) ? $model['name'] : 'unknown';
                    $size = isset($model['size']) ? $this->formatBytes($model['size']) : 'unknown';
                    $this->line("    - {$name} ({$size})");
                }

                // Check if configured model is available
                if ($requiredModel) {
                    $modelFound = false;
                    foreach ($models as $model) {
                        $modelName = isset($model['name']) ? $model['name'] : '';
                        if (str_starts_with($modelName, $requiredModel)) {
                            $modelFound = true;
                            break;
                        }
                    }

                    if (!$modelFound) {
                        $this->line("  <fg=yellow>Warning: Required model '{$requiredModel}' not found for profile '{$profile}'!</>");
                        $this->line("  Run: ./pull-ollama-model.sh {$profile}");
                    }
                }
            } else {
                $this->line('  <fg=yellow>No models installed</>');
                $this->line("  Run: ./pull-ollama-model.sh {$profile}");
            }
        } else {
            $this->line('✗ Ollama: <fg=red>Unavailable</>');
            $allOk = false;
        }

        // Check Database
        $this->info('Checking Database...');
        try {
            \DB::connection()->getPdo();
            $dbName = \DB::connection()->getDatabaseName();
            $this->line("✓ Database: <fg=green>Available</> ({$dbName})");
        } catch (\Exception $e) {
            $this->line('✗ Database: <fg=red>Unavailable</> - ' . $e->getMessage());
            $allOk = false;
        }

        // Check Redis (Optional - not required for worker)
        $this->info('Checking Redis...');
        try {
            Redis::connection()->ping();
            $this->line('✓ Redis: <fg=green>Available</>');
        } catch (\Exception $e) {
            $this->line('⚠ Redis: <fg=yellow>Unavailable</> (optional) - ' . $e->getMessage());
            // Not failing the check since Redis is optional
        }

        $this->newLine();

        if ($allOk) {
            $this->info('✓ All services are available!');
            $this->info('Ready to start worker: php artisan worker:consume');
            return Command::SUCCESS;
        } else {
            $this->error('✗ Some services are unavailable. Please check the logs.');
            return Command::FAILURE;
        }
    }

    /**
     * Format bytes to human readable format
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }
}
