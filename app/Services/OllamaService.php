<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    /**
     * Generate text using Ollama LLM
     *
     * @param string $prompt
     * @param array $images Base64 encoded images (optional)
     * @return string Generated response
     * @throws \Exception
     */
    public function generate(string $prompt, array $images = []): string
    {
        $profile = config('ollama.profile');
        $model = $this->getModelFromProfile($profile);
        $url = config('ollama.url');
        $timeout = config('ollama.timeout');
        $endpoint = config('ollama.endpoints.generate');

        $startTime = microtime(true);

        try {
            Log::debug('[Ollama] Starting generation', [
                'profile' => $profile,
                'model' => $model,
                'prompt_length' => strlen($prompt),
                'images_count' => count($images),
            ]);

            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($url . $endpoint, [
                    'model' => $model,
                    'prompt' => $prompt,
                    'images' => $images,
                    'stream' => false,
                ]);

            $executionTime = microtime(true) - $startTime;

            if (!$response->successful()) {
                throw new \Exception(
                    "Ollama API request failed with status {$response->status()}: {$response->body()}"
                );
            }

            $data = $response->json();

            if (!isset($data['response'])) {
                throw new \Exception('Invalid Ollama response: missing response field');
            }

            $responseText = $data['response'];

            Log::info(sprintf(
                '[Ollama] Profile=%s Model=%s Time=%.1fs',
                $profile,
                $model,
                $executionTime
            ), [
                'profile' => $profile,
                'model' => $model,
                'execution_time' => $executionTime,
                'prompt_length' => strlen($prompt),
                'response_length' => strlen($responseText),
                'images_count' => count($images),
            ]);

            return $responseText;
        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;

            Log::error('[Ollama] Generation failed', [
                'profile' => $profile,
                'model' => $model,
                'url' => $url,
                'prompt_length' => strlen($prompt),
                'images_count' => count($images),
                'execution_time' => $executionTime,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw exception to let it propagate
            throw $e;
        }
    }

    /**
     * Get model name from profile
     *
     * @param string $profile
     * @return string
     * @throws \Exception
     */
    private function getModelFromProfile(string $profile): string
    {
        $profiles = config('ollama.profiles');

        if (!isset($profiles[$profile])) {
            throw new \Exception("Invalid Ollama profile: {$profile}. Available: " . implode(', ', array_keys($profiles)));
        }

        return $profiles[$profile]['model'];
    }

    /**
     * Check if Ollama service is available
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        try {
            $url = config('ollama.url');
            $response = Http::timeout(5)->get($url . '/api/tags');
            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('[Ollama] Service not available', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get available models
     *
     * @return array
     */
    public function getAvailableModels(): array
    {
        try {
            $url = config('ollama.url');
            $response = Http::timeout(5)->get($url . '/api/tags');

            if (!$response->successful()) {
                return [];
            }

            $data = $response->json();
            return $data['models'] ?? [];
        } catch (\Exception $e) {
            Log::warning('[Ollama] Failed to get available models', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
