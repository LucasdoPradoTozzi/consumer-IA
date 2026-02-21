<?php

namespace App\Services;

use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\LlmUsageLog;
use Illuminate\Support\Facades\Log;

class LlmService
{
    /**
     * Generate text using the best available model for the given capability.
     *
     * @param string $prompt     The prompt to send
     * @param array  $params     Extra params passed to the provider service
     * @param string $capability 'text', 'image', 'multimodal', 'open-weight'
     * @return string
     */
    public function generateText(string $prompt, array $params = [], string $capability = 'text'): string
    {
        $model = $this->resolveModel($capability);

        Log::info('[LlmService] Model resolved', [
            'capability' => $capability,
            'model' => $model->name,
            'provider' => $model->provider->slug,
        ]);

        return $this->dispatch($model, $prompt, $params, $capability);
    }

    /**
     * Generate text from an image using a vision-capable model.
     *
     * @param string $prompt The prompt
     * @param array  $images Base64-encoded images
     * @param array  $params Extra params
     * @return string
     */
    public function generateFromImage(string $prompt, array $images, array $params = []): string
    {
        $model = $this->resolveModel('image');

        Log::info('[LlmService] Image model resolved', [
            'model' => $model->name,
            'provider' => $model->provider->slug,
            'images_count' => count($images),
        ]);

        return $this->dispatch($model, $prompt, array_merge($params, ['images' => $images]), 'image');
    }

    /**
     * Find the best available model for the given capability.
     * Ordered by provider priority, then model ranking.
     * Skips models that have exceeded their quotas.
     */
    private function resolveModel(string $capability): LlmModel
    {
        $candidates = LlmModel::query()
            ->where('llm_models.capability', $capability)
            ->where('llm_models.is_active', true)
            ->whereHas('provider', fn ($q) => $q->where('llm_providers.is_active', true))
            ->join('llm_providers', 'llm_models.llm_provider_id', '=', 'llm_providers.id')
            ->orderBy('llm_providers.priority', 'asc')
            ->orderBy('llm_models.ranking', 'asc')
            ->select('llm_models.*')
            ->get();

        if ($candidates->isEmpty()) {
            throw new \RuntimeException(
                "[LlmService] No active models found for capability: {$capability}"
            );
        }

        foreach ($candidates as $candidate) {
            if ($candidate->isWithinQuota()) {
                return $candidate;
            }

            Log::warning('[LlmService] Model over quota, trying next', [
                'model' => $candidate->name,
                'usage_minute' => $candidate->getUsageLastMinute(),
                'quota_minute' => $candidate->quota_per_minute,
                'usage_day' => $candidate->getUsageToday(),
                'quota_day' => $candidate->quota_per_day,
            ]);
        }

        throw new \RuntimeException(
            "[LlmService] All models for capability '{$capability}' have exceeded their quotas."
        );
    }

    /**
     * Dispatch the call to the provider's service class.
     * Each provider service knows how to pass the model name to its API.
     */
    private function dispatch(LlmModel $model, string $prompt, array $params, string $capability): string
    {
        $provider = $model->provider;
        $service = $provider->resolveService();
        $startTime = microtime(true);

        Log::info('[LlmService] Dispatching to provider', [
            'provider' => $provider->slug,
            'service' => $provider->service_class,
            'model' => $model->name,
        ]);

        try {
            // Dispatch based on provider type
            if ($provider->slug === 'ollama') {
                $images = $params['images'] ?? [];
                $response = $service->generate($prompt, $images, $model->name);
            } else {
                // Google and other HTTP-API providers
                $rawResponse = $service->generateText($prompt, $params, $model->name);

                // Normalize response — Google returns array, we need string
                if (is_array($rawResponse)) {
                    if (isset($rawResponse['candidates'][0]['content']['parts'][0]['text'])) {
                        $response = $rawResponse['candidates'][0]['content']['parts'][0]['text'];
                    } else {
                        $response = json_encode($rawResponse);
                    }
                } else {
                    $response = (string) $rawResponse;
                }
            }

            $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);

            $this->logUsage($model, $capability, $elapsedMs, true);

            Log::info('[LlmService] Call successful', [
                'model' => $model->name,
                'elapsed_ms' => $elapsedMs,
                'response_length' => strlen($response),
            ]);

            return $response;

        } catch (\Throwable $e) {
            $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->logUsage($model, $capability, $elapsedMs, false, $e->getMessage());

            Log::error('[LlmService] Call failed', [
                'model' => $model->name,
                'provider' => $provider->slug,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Record a usage log entry.
     */
    private function logUsage(
        LlmModel $model,
        string $capability,
        int $responseTimeMs,
        bool $success,
        ?string $errorMessage = null
    ): void {
        try {
            LlmUsageLog::create([
                'llm_model_id' => $model->id,
                'capability' => $capability,
                'prompt_tokens' => null,
                'response_tokens' => null,
                'response_time_ms' => $responseTimeMs,
                'success' => $success,
                'error_message' => $errorMessage,
                'called_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Never let logging failure break the main flow
            Log::error('[LlmService] Failed to log usage', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
