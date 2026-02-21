<?php

namespace Database\Seeders;

use App\Models\LlmProvider;
use App\Models\LlmModel;
use Illuminate\Database\Seeder;

class LlmModelsSeeder extends Seeder
{
    public function run(): void
    {
        // --- Google AI Studio Provider ---
        $google = LlmProvider::updateOrCreate(
            ['slug' => 'google'],
            [
                'name' => 'Google AI Studio',
                'is_active' => true,
                'priority' => 1,
                'api_key_env_var' => 'GOOGLEAI_API_KEY',
                'service_class' => \App\Services\GoogleAiStudioService::class,
            ]
        );

        // --- Ollama Provider (inactive for now, no models seeded) ---
        LlmProvider::updateOrCreate(
            ['slug' => 'ollama'],
            [
                'name' => 'Ollama (Local)',
                'is_active' => false,
                'priority' => 2,
                'api_key_env_var' => null,
                'service_class' => \App\Services\OllamaService::class,
            ]
        );

        // --- Google Models ---
        $models = [
            // Text models
            ['name' => 'gemini-2.5-flash', 'capability' => 'text', 'ranking' => 1, 'quota_per_minute' => 5, 'quota_per_day' => 20, 'tokens_per_minute' => null],
        ];

        foreach ($models as $modelData) {
            LlmModel::updateOrCreate(
                [
                    'llm_provider_id' => $google->id,
                    'name' => $modelData['name'],
                ],
                [
                    'capability' => $modelData['capability'],
                    'ranking' => $modelData['ranking'],
                    'is_active' => true,
                    'quota_per_minute' => $modelData['quota_per_minute'],
                    'quota_per_day' => null,
                    'tokens_per_minute' => null,
                ]
            );
        }
    }
}
