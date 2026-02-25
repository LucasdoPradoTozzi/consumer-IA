<?php

namespace App\Services\Workers;

use App\Models\JobApplication;
use App\Models\JobApplicationVersion;
use App\Services\LlmService;
use App\Services\PromptBuilderService;
use App\Services\PdfService;
use Illuminate\Support\Facades\Log;

class GenerationWorker
{
    /**
     * Fields that must always come from the database (CandidateProfileBridge),
     * never from LLM output. Even if the LLM returns these, they are ignored.
     */
    private const STATIC_FIELDS = [
        'name',
        'age',
        'marital_status',
        'location',
        'phone_link',
        'phone',
        'email',
        'github',
        'github_display',
        'linkedin',
        'linkedin_display',
    ];

    public function __construct(
        private readonly LlmService $llm,
        private readonly PromptBuilderService $promptBuilder,
        private readonly PdfService $pdfService,
    ) {}

    public function process(JobApplication $jobApplication): void
    {
        // Get or prepare the version for this job
        $version = $jobApplication->versions()->latest()->first();
        $versionNumber = ($jobApplication->versions()->count() + 1);

        Log::info('[GenerationWorker] Version state', [
            'job_id'    => $jobApplication->id,
            'version'   => $version ? $version->id : null,
            'version_n' => $versionNumber,
        ]);

        // Resolved enriched job data directly from job_application columns
        $jobData = $this->buildJobDataFromApplication($jobApplication);
        $language = $jobApplication->language;

        $candidateProfile = config('candidate.profile')();

        Log::info('[GenerationWorker] Context loaded', [
            'job_id'   => $jobApplication->id,
            'language' => $language,
        ]);

        // If all fields except resume_path are filled, just generate PDF
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
            $resumeConfig = is_string($version->resume_data)
                ? (json_decode($version->resume_data, true) ?? [])
                : ($version->resume_data ?? []);

            $version->resume_path = $this->generateResumePdf($resumeConfig, $language, $jobApplication->id);
            $version->completed   = true;
            $version->save();

            Log::info('[GenerationWorker] PDF generated', ['version_id' => $version->id]);
            return;
        }

        // If already fully complete, mark completed if needed
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
            }
            return;
        }

        // If no version yet, create one
        if (!$version) {
            $version = $jobApplication->versions()->create([
                'version_number' => $versionNumber,
                'email_sent'     => false,
                'completed'      => false,
            ]);
            Log::info('[GenerationWorker] Nova version criada', [
                'version_id' => $version->id,
            ]);
        }

        // UNIFIED PROMPT: cover letter + email + resume in one call
        try {
            $startTime = microtime(true);

            Log::info('[GenerationWorker] Building unified application prompt');
            $unifiedPrompt = $this->promptBuilder->buildUnifiedApplicationPrompt($jobData, $candidateProfile, $language);
            $llmResponse   = $this->llm->generateText($unifiedPrompt);

            if (!is_string($llmResponse)) {
                $llmResponse = json_encode($llmResponse);
            }

            $json = $this->parseJsonResponse($llmResponse);

            $coverLetter  = $json['cover_letter']  ?? null;
            $emailSubject = $json['email_subject'] ?? null;
            $emailBody    = $json['email_body']    ?? null;
            $resumeConfig = $json['resume_config'] ?? null;

            if (!empty($coverLetter))  $version->cover_letter  = $coverLetter;
            if (!empty($emailSubject)) $version->email_subject = $emailSubject;
            if (!empty($emailBody))    $version->email_body    = $emailBody;

            if (!empty($resumeConfig)) {
                $version->resume_data = json_encode($resumeConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }

            // Generate resume PDF
            $resumePdfPath = $this->generateResumePdf($resumeConfig ?? [], $language, $jobApplication->id);
            $version->resume_path = $resumePdfPath;

            $allFilled = $coverLetter && $resumeConfig && $resumePdfPath && $emailSubject && $emailBody;
            if ($allFilled) {
                $version->completed = true;
            }
            $version->save();

            Log::info('[GenerationWorker] Generation completed', [
                'job_id'    => $jobApplication->id,
                'version_id'=> $version->id,
                'duration'  => round(microtime(true) - $startTime, 2),
            ]);
        } catch (\Throwable $e) {
            Log::error('[GenerationWorker] Error in process', [
                'job_id' => $jobApplication->id,
                'error'  => $e->getMessage(),
                'file'   => $e->getFile(),
                'line'   => $e->getLine(),
            ]);

            // Persist whatever was generated before the failure
            $version->save();

            throw $e;
        }
    }

    /**
     * Build a merged job data array from the flat columns on job_application
     * (used by the unified prompt builder).
     */
    private function buildJobDataFromApplication(JobApplication $jobApplication): array
    {
        $base = $jobApplication->job_data ?? [];

        return array_merge($base, array_filter([
            'title'            => $jobApplication->extracted_title,
            'company'          => $jobApplication->extracted_company,
            'description'      => $jobApplication->extracted_description,
            'required_skills'  => $jobApplication->required_skills,
            'location'         => $jobApplication->extracted_location,
            'salary'           => $jobApplication->extracted_salary,
            'employment_type'  => $jobApplication->employment_type,
            'language'         => $jobApplication->language,
            'company_data'     => $jobApplication->company_data,
            'extra_information'=> $jobApplication->extra_information,
        ], fn($v) => $v !== null));
    }

    /**
     * Generate the resume PDF using the merged config.
     */
    private function generateResumePdf(array $resumeConfig, ?string $language, int $jobId): string
    {
        $bridge     = new \App\Services\CandidateProfileBridge();
        $lang       = ($language === 'en' || $language === 'english') ? 'en' : 'pt';
        $baseConfig = $bridge->getMappedProfile($lang) ?? [];

        $merged     = $this->mergeResumeConfig($baseConfig, $resumeConfig);
        $normalized = $this->normalizeResumeConfig($merged);

        return $this->pdfService->generateCurriculumPdf($normalized, $jobId);
    }

    /**
     * Merge base config (from database) with LLM-generated resume config,
     * ensuring that static/personal fields are never overwritten by LLM output.
     */
    public function mergeResumeConfig(array $baseConfig, array $llmConfig): array
    {
        $filteredLlmConfig = array_diff_key($llmConfig, array_flip(self::STATIC_FIELDS));
        return array_merge($baseConfig, $filteredLlmConfig);
    }

    private function normalizeResumeConfig(array $config): array
    {
        $defaults = [
            'name'             => '',
            'subtitle'         => '',
            'age'              => '',
            'marital_status'   => '',
            'location'         => '',
            'phone'            => '',
            'phone_link'       => '',
            'email'            => '',
            'github'           => '',
            'github_display'   => '',
            'linkedin'         => '',
            'linkedin_display' => '',
            'objective'        => '',
            'skills'           => [],
            'languages'        => [],
            'experience'       => [],
            'education'        => [],
            'projects'         => [],
            'certificates'     => [],
            'personal_info'    => [],
            'summary'          => '',
            'skills_categories'=> [],
            'certifications'   => [],
        ];

        $normalized = array_merge($defaults, $config);

        foreach (['skills', 'languages', 'experience', 'education', 'projects', 'certificates', 'skills_categories', 'certifications'] as $field) {
            if (!is_array($normalized[$field] ?? null)) {
                $normalized[$field] = [];
            }
        }

        return $normalized;
    }

    private function parseJsonResponse(string $response): array
    {
        $trimmed = trim($response);
        $start   = strpos($trimmed, '{');
        $end     = strrpos($trimmed, '}');

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
