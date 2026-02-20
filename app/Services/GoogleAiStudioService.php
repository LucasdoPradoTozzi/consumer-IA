<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleAiStudioService
{
    protected $apiKey;
    protected $endpoint;
    protected $model;
    protected $timeout;

    public function __construct()
    {
        $this->apiKey = config('googleai.api_key');
        $this->endpoint = config('googleai.endpoint', 'https://generativelanguage.googleapis.com/v1beta/models');
        $this->model = config('googleai.model', 'gemini-pro');
        $this->timeout = config('googleai.timeout', 30);
    }

    public function generateText($prompt, $params = [])
    {
        $url = $this->endpoint . "/{$this->model}:generateContent?key={$this->apiKey}";
        $payload = array_merge([
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ]
        ], $params);

        $response = Http::timeout($this->timeout)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $payload);

        if ($response->successful()) {
            return $response->json();
        }
        throw new \Exception('Google AI Studio API error: ' . $response->body());
    }
}
