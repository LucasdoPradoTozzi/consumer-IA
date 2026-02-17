<?php

namespace App\Services\Workers;

use App\Models\JobApplication;
use App\Models\JobApplicationVersion;
use App\Models\JobScoring;
use App\Services\OllamaService;
use App\Services\PromptBuilderService;
use App\Services\PdfService;
use Illuminate\Support\Facades\Log;

class GenerationWorker
{
    public function __construct(
        private readonly OllamaService $ollama,
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
        Log::info('[GenerationWorker] Versão buscada para scoring', [
            'scoring_id' => $scoring->id,
            'version' => $version ? $version->id : null,
        ]);

        $versionNumber = $scoring->versions()->count() + 1;
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

        $coverLetter = null;
        $resumeAdjustments = null;
        $coverLetterPdfPath = null;
        $resumePdfPath = null;

        try {
            $startTime = microtime(true);

            if (!$version || !$version->cover_letter) {
                Log::info('[GenerationWorker] Building cover letter prompt');
                // IMPORTANTE: Usar extractionData (dados limpos pela IA)
                $coverLetterPrompt = $this->promptBuilder->buildCoverLetterPrompt($extractionData, $candidateProfile);
                $coverLetterRaw = $this->ollama->generate($coverLetterPrompt);
                $coverLetter = $this->extractContent($coverLetterRaw, 'cover letter');
                Log::info('[GenerationWorker] Cover letter generated');
            } else {
                $coverLetter = $version->cover_letter;
            }

            // Generate resume adjustments
            $resumeConfig = null;
            if (!$version || !$version->resume_data) {
                Log::info('[GenerationWorker] Building resume adjustment prompt');
                // Aqui já usamos o extractionData carregado no início
                $resumePrompt = $this->promptBuilder->buildResumeAdjustmentPromptWithExamples($extractionData, $candidateProfile, $language);
                Log::info('[GenerationWorker] Resume adjustment prompt built', [
                    'prompt' => $resumePrompt,
                    'prompt_length' => strlen($resumePrompt),
                ]);
                $resumeResponse = $this->ollama->generate($resumePrompt);
                if (!is_string($resumeResponse)) {
                    $resumeResponse = json_encode($resumeResponse);
                }
                // Extrair resume_config do JSON retornado pelo LLM
                $resumeJson = json_decode($resumeResponse, true);
                if (is_array($resumeJson) && isset($resumeJson['resume_config'])) {
                    $resumeConfig = $resumeJson['resume_config'];
                }
                $resumeAdjustments = $this->extractContent($resumeResponse, 'resume adjustments');
                if (!is_string($resumeAdjustments)) {
                    $resumeAdjustments = json_encode($resumeAdjustments);
                }
                Log::info('[GenerationWorker] Resume adjustments extracted', [
                    'length' => strlen($resumeAdjustments),
                    'preview' => substr($resumeAdjustments, 0, 100),
                    'resume_config' => $resumeConfig,
                ]);
            } else {
                $resumeAdjustments = $version->resume_data;
                $resumeConfig = $version->resume_config ?? null;
                Log::info('[GenerationWorker] Using existing resume_data and resume_config from version', [
                    'version_id' => $version->id,
                    'resume_data_length' => is_string($resumeAdjustments) ? strlen($resumeAdjustments) : null,
                    'resume_config' => $resumeConfig,
                ]);
            }

            // Generate PDFs if missing
            if (!$version || !$version->resume_path) {
                Log::info('[GenerationWorker] Generating PDFs');
                
                // PEGAR BASE DO JSON SEGURO (Língua correta) via Bridge
                $bridge = new \App\Services\CandidateProfileBridge();
                $baseConfig = $bridge->getMappedProfile(($language === 'en' || $language === 'english') ? 'en' : 'pt');

                // GARANTIA: Merge o config base com os ajustes da IA
                $mergedConfig = array_merge($baseConfig ?? [], $resumeConfig ?? []);
                
                Log::info('[GenerationWorker] Dados mesclados para o currículo (via Bridge)', [
                    'has_resume_config' => !empty($resumeConfig),
                    'language' => $language,
                    'is_pii_secure' => true
                ]);

                $coverLetterPdfPath = $this->pdfService->generateCoverLetterPdf(
                    $coverLetter,
                    $extractionData,
                    $candidateProfile,
                    $jobApplication->id
                );

                $resumePdfPath = $this->pdfService->generateCurriculumPdf(
                    $mergedConfig,
                    $jobApplication->id
                );
                
                Log::info('[GenerationWorker] PDFs gerados com sucesso');
            } else {
                $resumePdfPath = $version->resume_path;
                $coverLetterPdfPath = $version->cover_letter_pdf_path ?? null;
            }

            // Generate email content
            $emailSubject = null;
            $emailBody = null;
            if (!$version || !$version->email_subject || $version->email_subject === 'Application for ' . ($jobData['title'] ?? 'Position')) {
                Log::info('[GenerationWorker] Building email application prompt');
                // Usar extractionData para o e-mail também
                $emailPrompt = $this->promptBuilder->buildEmailApplicationPrompt($extractionData, $candidateProfile, $language);
                $emailResponse = $this->ollama->generate($emailPrompt);
                
                try {
                    $emailData = $this->parseJsonResponse($emailResponse);
                    $emailSubject = $emailData['subject'] ?? null;
                    $emailBody = $emailData['body'] ?? null;
                } catch (\Exception $e) {
                    Log::warning('[GenerationWorker] Failed to parse email JSON, using fallback', ['error' => $e->getMessage()]);
                    $emailSubject = 'Application for ' . ($jobData['title'] ?? 'Position');
                    $emailBody = 'Please find attached my application materials.';
                }

                Log::info('[GenerationWorker] Email content generated', [
                    'subject' => $emailSubject,
                    'body_preview' => substr($emailBody, 0, 100),
                ]);
            } else {
                $emailSubject = $version->email_subject;
                $emailBody = $version->email_body;
            }

            $allFieldsFilled = $coverLetter && $resumeAdjustments && $resumePdfPath && $emailSubject && $emailBody;
            
            if (!$version) {
                $version = $jobApplication->versions()->create([
                    'scoring_id' => $scoring->id,
                    'version_number' => $versionNumber,
                    'cover_letter' => $coverLetter,
                    'email_subject' => $emailSubject,
                    'email_body' => $emailBody,
                    'resume_data' => is_string($resumeAdjustments) ? json_decode($resumeAdjustments, true) ?? $resumeAdjustments : $resumeAdjustments,
                    'resume_config' => $resumeConfig, // PERSISTIR AQUI
                    'resume_path' => $resumePdfPath,
                    'email_sent' => false,
                    'completed' => $allFieldsFilled ? true : false,
                ]);
            } else {
                $version->cover_letter = $coverLetter;
                $version->resume_data = is_string($resumeAdjustments) ? json_decode($resumeAdjustments, true) ?? $resumeAdjustments : $resumeAdjustments;
                $version->resume_config = $resumeConfig; // PERSISTIR AQUI
                $version->resume_path = $resumePdfPath;
                $version->email_subject = $emailSubject;
                $version->email_body = $emailBody;
                if ($allFieldsFilled) {
                    $version->completed = true;
                }
                $version->save();
            }
        } catch (\Throwable $e) {
            Log::error('[GenerationWorker] Error in processScoring', [
                'job_id' => $jobApplication->id,
                'scoring_id' => $scoring->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'cover_letter' => $coverLetter,
                'resume_adjustments' => $resumeAdjustments,
                'cover_letter_pdf_path' => $coverLetterPdfPath,
                'resume_pdf_path' => $resumePdfPath,
            ]);
            // Save partial version if any part was generated
            if ($coverLetter || $resumeAdjustments || $resumePdfPath) {
                if (!$version) {
                    $version = $jobApplication->versions()->create([
                        'scoring_id' => $scoring->id,
                        'version_number' => $versionNumber,
                        'cover_letter' => $coverLetter,
                        'email_subject' => 'Application for ' . ($jobData['title'] ?? 'Position'),
                        'email_body' => 'Please find attached my application materials.',
                        'resume_data' => is_string($resumeAdjustments) ? json_decode($resumeAdjustments, true) ?? $resumeAdjustments : $resumeAdjustments,
                        'resume_path' => $resumePdfPath,
                        'email_sent' => false,
                        'completed' => false,
                    ]);
                } else {
                    $version->cover_letter = $coverLetter;
                    $version->resume_data = is_string($resumeAdjustments) ? json_decode($resumeAdjustments, true) ?? $resumeAdjustments : $resumeAdjustments;
                    $version->resume_path = $resumePdfPath;
                    $version->save();
                }
            }
            throw $e;
        }
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
