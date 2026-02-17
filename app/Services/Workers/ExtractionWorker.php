<?php

namespace App\Services\Workers;

use App\Models\JobApplication;
use App\Models\JobExtraction;
use App\Services\OllamaService;
use App\Services\PromptBuilderService;
use Illuminate\Support\Facades\Log;

class ExtractionWorker
{
    public function __construct(
        private readonly OllamaService $ollama,
        private readonly PromptBuilderService $promptBuilder,
    ) {}

    /**
     * Extract text from images and enhance job data
     *
     * @param JobApplication $jobApplication
     * @return void
     */
    public function process(JobApplication $jobApplication): void
    {
        // Find versions with null extraction_data OR missing language metadata
        $pendingVersions = $jobApplication->extractions()
            ->where(function ($q) {
                $q->whereNull('extraction_data')
                  ->orWhereRaw("extraction_data->>'language' IS NULL");
            })
            ->get();

        if ($pendingVersions->isEmpty()) {
            Log::info('[ExtractionWorker] No pending extractions', [
                'application_id' => $jobApplication->id,
            ]);
            return;
        }

        foreach ($pendingVersions as $version) {
            $this->processVersion($jobApplication, $version);
        }
    }

    private function processVersion(JobApplication $jobApplication, JobExtraction $version): void
    {
        $startTime = microtime(true);
        $jobData = $jobApplication->job_data ?? [];

        Log::info('[ExtractionWorker] Starting extraction for version', [
            'application_id' => $jobApplication->id,
            'version' => $version->version_number,
        ]);

        try {
            // Handle image field - ensure it's a valid string
            $imageBase64 = $jobData['image'] ?? null;
            if (is_array($imageBase64)) {
                $imageBase64 = !empty($imageBase64) ? reset($imageBase64) : null;
            }

            $extractedText = null;
            if ($imageBase64 && is_string($imageBase64) && strlen($imageBase64) > 100) {
                if ($this->isValidBase64($imageBase64)) {
                    $extractedText = $this->extractTextFromImage($imageBase64, $jobApplication->id);

                    Log::info('[ExtractionWorker] Text extracted from image', [
                        'job_id' => $jobApplication->id,
                        'text_length' => strlen($extractedText),
                    ]);
                } else {
                    Log::warning('[ExtractionWorker] Invalid base64 image', [
                        'job_id' => $jobApplication->id,
                    ]);
                }
            }

            // Prepare job data for structured extraction
            $tempJobData = $jobData;
            if ($extractedText) {
                $tempJobData['description'] = ($jobData['description'] ?? '') . "\n\nExtracted from image:\n" . $extractedText;
            }

            // Call Ollama for structured extraction (includes language detection)
            $prompt = $this->promptBuilder->buildExtractionPrompt($tempJobData);
            $response = $this->ollama->generate($prompt);
            
            $structuredData = $this->parseJsonResponse($response);
            $extractedInfo = $structuredData['extracted_info'] ?? [];

            // Enhance job data with extracted information
            $extractionData = array_merge($jobData, $extractedInfo);
            if ($extractedText) {
                $extractionData['extracted_text'] = $extractedText;
            }

            // Save to version
            $version->update([
                'extraction_data' => $extractionData,
            ]);

            $duration = microtime(true) - $startTime;

            Log::info('[ExtractionWorker] Extraction completed for version', [
                'job_id' => $jobApplication->id,
                'version' => $version->version_number,
                'duration' => round($duration, 2),
                'language' => $extractedInfo['language'] ?? 'unknown',
            ]);
        } catch (\Exception $e) {
            Log::error('[ExtractionWorker] Extraction failed for version', [
                'job_id' => $jobApplication->id,
                'version' => $version->version_number,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Validate if string is valid base64
     */
    private function isValidBase64(string $string): bool
    {
        if (empty($string)) {
            return false;
        }

        // Check if it's valid base64
        if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $string)) {
            return false;
        }

        // Try to decode
        $decoded = base64_decode($string, true);
        return $decoded !== false && strlen($decoded) > 100;
    }

    /**
     * Extract text from base64 image using Ollama
     */
    private function extractTextFromImage(string $imageBase64, string $jobId): string
    {
        $prompt = "Extract all text from this image. Return only the text content, nothing else.";

        $response = $this->ollama->generate($prompt, [$imageBase64]);

        return trim($response);
    }

    /**
     * Parse JSON response from LLM
     */
    private function parseJsonResponse(string $response): array
    {
        $trimmed = trim($response);
        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');

        if ($start === false || $end === false) {
            throw new \Exception('No JSON found in response: ' . $response);
        }

        $json = substr($trimmed, $start, $end - $start + 1);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON: ' . json_last_error_msg());
        }

        return $data;
    }
}
