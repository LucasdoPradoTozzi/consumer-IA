<?php

namespace App\Services\Workers;

use App\Models\JobApplication;
use App\Models\JobApplicationVersion;
use App\Models\JobScoring;
use App\Services\LlmService;
use App\Services\PromptBuilderService;
use App\Services\PdfService;
use Illuminate\Support\Facades\Log;

class GenerationWorker
{
    public function __construct(
        private readonly LlmService $llm,
        private readonly PromptBuilderService $promptBuilder,
        private readonly PdfService $pdfService,
    ) {}

    public function process(JobApplication $jobApplication): void
    {
        // Buscar versão existente para este scoring
        $scoring = $jobApplication->scorings()->latest()->first();
        if (!$scoring) {
            Log::warning('[GenerationWorker] Nenhum scoring encontrado para o job', [
                'job_id' => $jobApplication->id,
            ]);
            return;
        }

        $version = $jobApplication->versions()->where('scoring_id', $scoring->id)->first();
        $versionNumber = $scoring->versions()->count() + 1;
        Log::info('[GenerationWorker] Versão buscada para scoring', [
            'scoring_id' => $scoring->id,
            'version' => $version ? $version->id : null,
        ]);
        Log::info('[GenerationWorker] Próximo version_number calculado', [
            'scoring_id' => $scoring->id,
            'version_number' => $versionNumber,
        ]);

        $jobData = $jobApplication->job_data ?? [];
        Log::info('[GenerationWorker] job_data carregado', [
            'job_id' => $jobApplication->id,
            'job_data_keys' => is_array($jobData) ? array_keys($jobData) : [],
        ]);

        $candidateProfile = config('candidate.profile')();
        Log::info('[GenerationWorker] candidateProfile carregado');

        // Buscar enriquecimento da extração ANTES de gerar qualquer coisa
        $latestExtraction = $jobApplication->extractions()->latest()->first();
        $extractionData = $latestExtraction ? ($latestExtraction->extraction_data ?? $jobData) : $jobData;
        $language = $latestExtraction ? ($latestExtraction->extraction_data['language'] ?? null) : null;

        Log::info('[GenerationWorker] Contexto de extração carregado', [
            'language' => $language,
            'extraction_id' => $latestExtraction ? $latestExtraction->id : null,
            'using_enriched_data' => $latestExtraction && $latestExtraction->extraction_data ? true : false,
        ]);

        // If all fields except resume_path are filled, just generate PDF and mark as completed
        if (
            $version
            && $version->cover_letter
            && $version->email_subject
            && $version->email_body
            && $version->resume_data
            && empty($version->resume_path)
        ) {
            Log::info('[GenerationWorker] All fields present except resume_path, generating PDF only', [
                'version_id' => $version->id,
            ]);
            // Try to decode resume_data as JSON, fallback to array
            $resumeConfig = null;
            $resumeData = $version->resume_data;
            if (is_string($resumeData)) {
                $resumeConfig = json_decode($resumeData, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $resumeConfig = [];
                }
            } elseif (is_array($resumeData)) {
                $resumeConfig = $resumeData;
            } else {
                $resumeConfig = [];
            }
            $bridge = new \App\Services\CandidateProfileBridge();
            $language = $jobApplication->extractions()->latest()->first()?->extraction_data['language'] ?? null;
            $baseConfig = $bridge->getMappedProfile(($language === 'en' || $language === 'english') ? 'en' : 'pt');
            $mergedConfig = array_merge($baseConfig ?? [], $resumeConfig ?? []);
            $normalizedConfig = $this->normalizeResumeConfig($mergedConfig);
            $resumePdfPath = $this->pdfService->generateCurriculumPdf(
                $normalizedConfig,
                $jobApplication->id
            );
            $version->resume_path = $resumePdfPath;
            $version->completed = true;
            $version->save();
            Log::info('[GenerationWorker] PDF generated and version marked as completed', [
                'version_id' => $version->id,
                'resume_path' => $resumePdfPath,
            ]);
            return;
        }

        // If we don't have a version yet, create one before proceeding
        if (!$version) {
            $version = $jobApplication->versions()->create([
                'scoring_id' => $scoring->id,
                'version_number' => $versionNumber,
                'cover_letter' => null,
                'email_subject' => null,
                'email_body' => null,
                'resume_data' => null,
                'resume_config' => null,
                'email_sent' => false,
                'completed' => false,
            ]);
            Log::info('[GenerationWorker] Nova versão criada para scoring', [
                'scoring_id' => $scoring->id,
                'version_id' => $version->id,
            ]);
        }

        // If all fields including resume_path are filled, just mark as completed if not already
        if (
            $version
            && $version->cover_letter
            && $version->email_subject
            && $version->email_body
            && $version->resume_data
            && $version->resume_path
        ) {
            if (!$version->completed) {
                $version->completed = true;
                $version->save();
                Log::info('[GenerationWorker] Versão já estava completa, marcada como completed', [
                    'version_id' => $version->id,
                ]);
            } else {
                Log::info('[GenerationWorker] Versão já estava completa e completed', [
                    'version_id' => $version->id,
                ]);
            }
            return;
        }

        // NOVO FLUXO: Prompt unificado
        try {
            $startTime = microtime(true);

            Log::info('[GenerationWorker] Building unified application prompt');
            $unifiedPrompt = $this->promptBuilder->buildUnifiedApplicationPrompt($extractionData, $candidateProfile, $language);
            Log::info('[GenerationWorker] Unified prompt built', [
                'prompt' => $unifiedPrompt,
                'prompt_length' => strlen($unifiedPrompt),
            ]);
            $llmResponse = $this->llm->generateText($unifiedPrompt);
            if (!is_string($llmResponse)) {
                $llmResponse = json_encode($llmResponse);
            }
            $json = $this->parseJsonResponse($llmResponse);

            $coverLetter = $json['cover_letter'] ?? null;
            if (!empty($coverLetter)) {
                $version->cover_letter = $coverLetter;
            }
            $emailSubject = $json['email_subject'] ?? null;
            $emailBody = $json['email_body'] ?? null;
            if (!empty($emailSubject)) {
                $version->email_subject = $emailSubject;
            }

            $emailBody = $json['email_body'] ?? null;
            if (!empty($emailBody)) {
                $version->email_body = $emailBody;
            }

            $resumeConfig = $json['resume_config'] ?? null;
            if (!empty($resumeConfig)) {
                $version->resume_data = json_encode($resumeConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
            $resumeAdjustments = $resumeConfig ? json_encode($resumeConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : null;

            // Gerar PDFs
            $bridge = new \App\Services\CandidateProfileBridge();
            $baseConfig = $bridge->getMappedProfile(($language === 'en' || $language === 'english') ? 'en' : 'pt');
            $mergedConfig = array_merge($baseConfig ?? [], $resumeConfig ?? []);
            $normalizedConfig = $this->normalizeResumeConfig($mergedConfig);
            $resumePdfPath = $this->pdfService->generateCurriculumPdf(
                $normalizedConfig,
                $jobApplication->id
            );
            $version->resume_path = $resumePdfPath;

            $allFieldsFilled = $coverLetter && $resumeAdjustments && $resumePdfPath && $emailSubject && $emailBody;

            if ($allFieldsFilled) {
                $version->completed = true;
            }
            $version->save();
        } catch (\Throwable $e) {
            Log::error('[GenerationWorker] Error in processScoring', [
                'job_id' => $jobApplication->id,
                'scoring_id' => $scoring->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Salva informações geradas mesmo em caso de erro
            if (!isset($version) || !$version) {
                $version = $jobApplication->versions()->create([
                    'scoring_id' => $scoring->id,
                    'version_number' => $versionNumber,
                    'cover_letter' => $coverLetter ?? null,
                    'email_subject' => $emailSubject ?? null,
                    'email_body' => $emailBody ?? null,
                    'resume_data' => $resumeAdjustments ?? null,
                    'resume_config' => $resumeConfig ?? null,
                    'email_sent' => false,
                    'completed' => false,
                ]);
            } else {
                if (!empty($coverLetter)) $version->cover_letter = $coverLetter;
                if (!empty($emailSubject)) $version->email_subject = $emailSubject;
                if (!empty($emailBody)) $version->email_body = $emailBody;
                if (!empty($resumeAdjustments)) $version->resume_data = $resumeAdjustments;
                if (!empty($resumeConfig)) $version->resume_config = $resumeConfig;
                $version->save();
            }
            throw $e;
        }
    }

    // Função de normalização para resume_config
    private function normalizeResumeConfig(array $config): array
    {
        $defaults = [
            'name' => '',
            'subtitle' => '',
            'age' => '',
            'marital_status' => '',
            'location' => '',
            'phone' => '',
            'phone_link' => '',
            'email' => '',
            'github' => '',
            'github_display' => '',
            'linkedin' => '',
            'linkedin_display' => '',
            'objective' => '',
            'skills' => [],
            'languages' => [],
            'experience' => [],
            'education' => [],
            'projects' => [],
            'certificates' => [],
            'personal_info' => [],
            'summary' => '',
            'skills_categories' => [],
            'certifications' => [],
        ];
        // Preencher campos faltantes
        $normalized = array_merge($defaults, $config);
        // Corrigir tipos
        foreach (['skills', 'languages', 'experience', 'education', 'projects', 'certificates', 'skills_categories', 'certifications'] as $field) {
            if (!isset($normalized[$field]) || !is_array($normalized[$field])) {
                $normalized[$field] = [];
            }
        }
        return $normalized;
    }

    /**
     * Extract content from LLM response
     */
    private function extractContent(string $response, string $type): string
    {
        $trimmed = trim($response);

        // Try to find JSON content first
        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');

        if ($start !== false && $end !== false) {
            $json = substr($trimmed, $start, $end - $start + 1);
            $data = json_decode($json, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($data['content'])) {
                $content = $data['content'];
                if (is_array($content)) {
                    $content = implode("\n", $content);
                }
                return $content;
            }

            // Check for specific keys based on type
            $key = match ($type) {
                'cover letter' => 'cover_letter',
                'resume adjustments' => 'adjusted_resume',
                default => 'content',
            };

            if (isset($data[$key])) {
                $content = $data[$key];
                if (is_array($content)) {
                    $content = implode("\n", $content);
                }
                return $content;
            }
        }
        // Fallback to raw text
        return $trimmed;
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
