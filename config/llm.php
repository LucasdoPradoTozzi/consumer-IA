<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LLM Orchestration Configuration
    |--------------------------------------------------------------------------
    |
    | The LLM orchestration layer selects the best model automatically based on
    | capability requested, provider priority, and quota availability.
    |
    | Provider and model data lives in the database (llm_providers, llm_models).
    | Use `php artisan db:seed --class=LlmModelsSeeder` to populate.
    |
    */

    'default_capability' => 'text',
];
