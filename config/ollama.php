<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ollama Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Ollama LLM integration. All values are loaded from .env
    | and should be accessed via config('ollama.key') in services.
    |
    */

    'url' => env('OLLAMA_URL', 'http://localhost:11434'),

    'profile' => env('OLLAMA_PROFILE', 'low'),

    'timeout' => env('OLLAMA_TIMEOUT', 600),

    /*
    |--------------------------------------------------------------------------
    | Model Profiles
    |--------------------------------------------------------------------------
    |
    | Different model profiles for different hardware capabilities:
    | - low: Lightweight model for development/testing (lower RAM/VRAM)
    | - medium: Balanced model for moderate hardware
    | - high: Production model with vision capabilities (requires 32GB+ RAM)
    |
    */

    'profiles' => [
        'low' => [
            'model' => 'qwen2.5:7b',
            'description' => 'Lightweight 7B model - ~4GB RAM',
        ],
        'medium' => [
            'model' => 'qwen2.5:14b',
            'description' => 'Balanced 14B model - ~8GB RAM',
        ],
        'high' => [
            'model' => 'qwen2.5:32b',
            'description' => 'Production 32B model - ~20GB RAM',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    */

    'endpoints' => [
        'generate' => '/api/generate',
        'chat' => '/api/chat',
        'embeddings' => '/api/embeddings',
    ],

];
