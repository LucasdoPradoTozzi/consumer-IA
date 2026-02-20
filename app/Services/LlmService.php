<?php

namespace App\Services;

use App\Services\GoogleAiStudioService;
// use App\Services\OllamaService; // Para futuro uso

class LlmService
{
    protected $provider;
    protected $google;
    // protected $ollama;

    public function __construct(GoogleAiStudioService $google)
    {
        $this->provider = config('llm.provider', 'google');
        $this->google = $google;
        // $this->ollama = $ollama;
    }

    /**
     * Gera texto usando o provedor configurado.
     * Por hora, todas as requisições vão para o Google.
     */
    public function generateText($prompt, $params = [])
    {
        $provider = config('llm.provider', 'google');
        \Log::info('[LlmService] Prompt enviado ao LLM', [
            'provider' => $provider,
            'prompt' => $prompt,
            'params' => $params,
        ]);
        if ($provider === 'google') {
            $response = $this->google->generateText($prompt, $params);
            \Log::info('[LlmService] Resposta recebida do LLM', [
                'provider' => $provider,
                'response' => $response,
            ]);
            // Normaliza resposta do Google Gemini
            if (
                is_array($response)
                && isset($response['candidates'][0]['content']['parts'][0]['text'])
            ) {
                return $response['candidates'][0]['content']['parts'][0]['text'];
            }
            // fallback: retorna json string
            return json_encode($response);
        }
        // Futuro: adicionar suporte ao Ollama
        // return $this->ollama->generate($prompt, $params['images'] ?? []);
        throw new \Exception('LLM provider não suportado: ' . $provider);
    }

    // Métodos adicionais podem ser roteados aqui (ex: vision, embeddings, etc)
}
