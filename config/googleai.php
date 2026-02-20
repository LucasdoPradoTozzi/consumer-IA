<?php

return [
    'api_key' => env('GOOGLEAI_API_KEY'),
    'endpoint' => env('GOOGLEAI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models'),
    'model' => env('GOOGLEAI_MODEL', 'gemini-pro'),
    'timeout' => env('GOOGLEAI_TIMEOUT', 30),
];
