<?php

namespace App\Services;

class PromptBuilderService
{
  /**
   * Build resume adjustment prompt with dynamic examples (pt/en)
   * Returns JSON with: { "adjusted_resume": string, "changes_made": array }
   *
   * @param array $jobData Job information
   * @param array $candidateProfile Candidate profile data
   * @return string
   */
  public function buildResumeAdjustmentPromptWithExamples(array $jobData, array $candidateProfile, ?string $language = null): string
  {
    // Normalize language (portuguese, english, pt, en)
    $lang = strtolower($language ?? 'pt');
    if ($lang === 'portuguese') $lang = 'pt';
    if ($lang === 'english') $lang = 'en';
    
    // Default to 'pt' if not en
    if ($lang !== 'en') $lang = 'pt';

    // Carregar exemplos reais do config
    $examplePt = config('curriculum.default_candidate');
    $exampleEn = config('curriculum_en.default_candidate');
    $examplePtJson = json_encode(['lang' => 'pt'] + $examplePt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $exampleEnJson = json_encode(['lang' => 'en'] + $exampleEn, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // Serializar jobData (vaga) e candidateProfile como JSON
    $jobDataJson = json_encode($jobData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $candidateProfileJson = json_encode($candidateProfile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    $promptTemplate = config('prompts.resume_adjustment.prompt');
    
    // Decidir qual exemplo incluir
    if ($lang === 'pt') {
        $exemploDinamico = "Exemplo de preenchimento em português (deve ser usado como base):\n" . $examplePtJson;
    } else {
        $exemploDinamico = "Example of filling in English (must be used as base):\n" . $exampleEnJson;
    }

    return str_replace([
        '[EXEMPLO_DINAMICO]',
        '{candidateProfile}',
        '{jobData}'
    ], [
        $exemploDinamico,
        $candidateProfileJson,
        $jobDataJson
    ], $promptTemplate);
}

/**
 * Build email application prompt
 * Returns JSON with: { "subject": "string", "body": "string" }
 *
 * @param array $jobData Job information
 * @param array $candidateProfile Candidate profile data
 * @param string|null $language Language (pt, en)
 * @return string
 */
public function buildEmailApplicationPrompt(array $jobData, array $candidateProfile, ?string $language = null): string
{
    $lang = strtolower($language ?? 'pt');
    if ($lang === 'portuguese') $lang = 'pt';
    if ($lang === 'english') $lang = 'en';
    if ($lang !== 'en') $lang = 'pt';

    $jobDataJson = json_encode($jobData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $candidateProfileJson = json_encode($candidateProfile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    $promptTemplate = config('prompts.email.prompt');
    
    $prompt = str_replace([
        '{jobData}',
        '{candidateProfile}',
        '{language}'
    ], [
        $jobDataJson,
        $candidateProfileJson,
        $lang === 'en' ? 'Inglês' : 'Português'
    ], $promptTemplate);

    return $prompt;
}
  /**
   * Build extraction prompt
   * Returns JSON with: { "extracted_info": object }
   *
   * @param array $jobData Job information
   * @return string
   */
  public function buildExtractionPrompt(array $jobData): string
  {
    $jobTitle = $jobData['title'] ?? 'Unknown';
    $company = $jobData['company'] ?? 'Unknown';
    $jobDescription = $jobData['description'] ?? '';

    $promptTemplate = config('prompts.extraction.prompt');

    return str_replace([
      '{jobTitle}',
      '{company}',
      '{jobDescription}'
    ], [
      $jobTitle,
      $company,
      $jobDescription
    ], $promptTemplate);
  }

  /**
   * Build classification prompt
   * Returns JSON with: { "is_relevant": boolean, "reason": string }
   *
   * @param array $jobData Job information
   * @param string|null $extractedText Text extracted from image (optional)
   * @return string
   */
  public function buildClassificationPrompt(array $jobData, ?string $extractedText = null): string
  {
    $jobDescription = $jobData['description'] ?? '';
    $jobTitle = $jobData['title'] ?? 'Unknown';
    $company = $jobData['company'] ?? 'Unknown';

    $context = "Job Title: {$jobTitle}\nCompany: {$company}\nDescription: {$jobDescription}";

    if ($extractedText) {
      $context .= "\n\nExtracted Text from Image:\n{$extractedText}";
    }

    $promptTemplate = config('prompts.classification.prompt');

    return str_replace('{context}', $context, $promptTemplate);
  }

  /**
   * Build scoring prompt
   * Returns JSON with: { "score": number (0-100), "justification": string }
   *
   * @param array $jobData Job information
   * @param array $candidateProfile Candidate profile/resume data
   * @return string
   */
  public function buildScorePrompt(array $jobData, array $candidateProfile): string
  {
    $jobDescription = $jobData['description'] ?? '';
    $jobTitle = $jobData['title'] ?? 'Unknown';
    $requiredSkills = $jobData['required_skills'] ?? [];
    $jobLanguage = $jobData['language'] ?? null;

    $candidateName = $candidateProfile['name'] ?? 'Candidate';
    $candidateSkills = $candidateProfile['skills'] ?? [];
    $candidateExperience = $candidateProfile['experience'] ?? '';
    $candidateExperienceText = is_array($candidateExperience) ? 'Has professional experience' : ($candidateExperience ?: 'No experience specified');

    // Flatten candidate skills
    $candidateSkillsFlat = [];
    if (is_array($candidateSkills)) {
      foreach ($candidateSkills as $category => $skills) {
        if (is_array($skills)) {
          foreach ($skills as $skill) {
            if (is_array($skill) && isset($skill['name'])) {
              $candidateSkillsFlat[] = $skill['name'];
            } elseif (is_string($skill)) {
              $candidateSkillsFlat[] = $skill;
            }
          }
        }
      }
    }

    $skillsText = empty($requiredSkills) ? 'Not specified' : implode(', ', $requiredSkills);
    $candidateSkillsText = empty($candidateSkillsFlat) ? 'Not specified' : implode(', ', $candidateSkillsFlat);

    $languageInstruction = $jobLanguage ? "\nLanguage of the job posting: {$jobLanguage}" : "";
    $promptTemplate = config('prompts.scoring.prompt');

    return str_replace([
      '{languageInstruction}',
      '{jobTitle}',
      '{skillsText}',
      '{jobDescription}',
      '{candidateName}',
      '{candidateSkillsText}',
      '{candidateExperienceText}'
    ], [
      $languageInstruction,
      $jobTitle,
      $skillsText,
      $jobDescription,
      $candidateName,
      $candidateSkillsText,
      $candidateExperienceText
    ], $promptTemplate);
  }

  /**
   * Build cover letter generation prompt
   * Returns JSON with: { "cover_letter": string }
   *
   * @param array $jobData Job information
   * @param array $candidateProfile Candidate profile data
   * @return string
   */
  public function buildCoverLetterPrompt(array $jobData, array $candidateProfile): string
  {
    $jobTitle = isset($jobData['title']) && !is_array($jobData['title']) ? $jobData['title'] : (is_array($jobData['title'] ?? null) ? json_encode($jobData['title']) : 'Unknown');
    $company = isset($jobData['company']) && !is_array($jobData['company']) ? $jobData['company'] : (is_array($jobData['company'] ?? null) ? json_encode($jobData['company']) : 'Unknown Company');
    $jobDescription = isset($jobData['description']) && !is_array($jobData['description']) ? $jobData['description'] : (is_array($jobData['description'] ?? null) ? json_encode($jobData['description']) : '');

    $candidateName = $candidateProfile['name'] ?? 'Candidate';
    $candidateSkills = $candidateProfile['skills'] ?? [];
    $candidateExperience = $candidateProfile['experience'] ?? '';

    // Flatten candidate skills
    $candidateSkillsFlat = [];
    if (is_array($candidateSkills)) {
      foreach ($candidateSkills as $category => $skills) {
        if (is_array($skills)) {
          foreach ($skills as $skill) {
            if (is_array($skill) && isset($skill['name'])) {
              $candidateSkillsFlat[] = $skill['name'];
            } elseif (is_string($skill)) {
              $candidateSkillsFlat[] = $skill;
            }
          }
        }
      }
    }

    $candidateSkillsText = empty($candidateSkillsFlat) ? 'Various skills' : implode(', ', $candidateSkillsFlat);
    if (is_array($candidateExperience)) {
      $candidateExperience = json_encode($candidateExperience);
    }

    $promptTemplate = config('prompts.cover_letter.prompt');

    return str_replace([
      '{jobTitle}',
      '{company}',
      '{jobDescription}',
      '{candidateName}',
      '{candidateSkillsText}',
      '{candidateExperience}'
    ], [
      $jobTitle,
      $company,
      $jobDescription,
      $candidateName,
      $candidateSkillsText,
      $candidateExperience
    ], $promptTemplate);
  }

  /**
   * Build resume adjustment prompt
   * Returns JSON with: { "adjusted_resume": string, "changes_made": array }
   *
   * @param array $jobData Job information
   * @param array $candidateProfile Candidate profile data
   * @return string
   */
  public function buildResumeAdjustmentPrompt(array $jobData, array $candidateProfile): string
  {
    // Defensive assignment for all possibly missing fields
    $requiredSkills = $jobData['required_skills'] ?? [];
    if (is_null($requiredSkills)) {
      $requiredSkills = [];
    }
    $currentResume = $candidateProfile['resume'] ?? '';
    if (is_null($currentResume)) {
      $currentResume = '';
    }
    $candidateSkills = $candidateProfile['skills'] ?? [];
    if (is_null($candidateSkills)) {
      $candidateSkills = [];
    }

    \Log::info('[PromptBuilder] buildResumeAdjustmentPrompt fields', [
      'jobTitle_type' => isset($jobData['title']) ? gettype($jobData['title']) : 'undefined',
      'jobTitle_value' => $jobData['title'] ?? null,
      'jobDescription_type' => isset($jobData['description']) ? gettype($jobData['description']) : 'undefined',
      'jobDescription_value' => $jobData['description'] ?? null,
      'requiredSkills_type' => gettype($requiredSkills),
      'requiredSkills_value' => is_array($requiredSkills) ? json_encode(array_slice($requiredSkills, 0, 3)) : $requiredSkills,
      'currentResume_type' => gettype($currentResume),
      'currentResume_value' => is_array($currentResume) ? json_encode($currentResume) : $currentResume,
      'candidateSkills_type' => gettype($candidateSkills),
      'candidateSkills_value' => is_array($candidateSkills) ? json_encode(array_slice($candidateSkills, 0, 3)) : $candidateSkills,
    ]);

    $jobTitle = isset($jobData['title']) && !is_array($jobData['title']) ? $jobData['title'] : (is_array($jobData['title'] ?? null) ? json_encode($jobData['title']) : 'Unknown');
    $jobDescription = isset($jobData['description']) && !is_array($jobData['description']) ? $jobData['description'] : (is_array($jobData['description'] ?? null) ? json_encode($jobData['description']) : '');
    $skillsText = empty($requiredSkills) ? 'Not specified' : (is_array($requiredSkills) ? implode(', ', $requiredSkills) : (string)$requiredSkills);
    $currentResume = is_array($currentResume) ? json_encode($currentResume) : (string)$currentResume;
    $candidateSkills = is_array($candidateSkills) ? json_encode($candidateSkills) : (string)$candidateSkills;

    \Log::info('[PromptBuilder] buildResumeAdjustmentPrompt sanitized fields', [
      'jobTitle_type' => gettype($jobTitle),
      'jobTitle_value' => $jobTitle,
      'jobDescription_type' => gettype($jobDescription),
      'jobDescription_value' => $jobDescription,
      'skillsText_type' => gettype($skillsText),
      'skillsText_value' => $skillsText,
      'currentResume_type' => gettype($currentResume),
      'currentResume_value' => $currentResume,
      'candidateSkills_type' => gettype($candidateSkills),
      'candidateSkills_value' => $candidateSkills,
    ]);

    $promptTemplate = config('prompts.resume_optimization.prompt');

    return str_replace([
      '{jobTitle}',
      '{jobDescription}',
      '{skillsText}',
      '{currentResume}',
      '{candidateSkills}'
    ], [
      $jobTitle,
      $jobDescription,
      $skillsText,
      $currentResume,
      $candidateSkills
    ], $promptTemplate);
  }

  /**
   * Build reclassification prompt with additional message context
   * Used when reprocessing a job with user feedback
   * Returns JSON with: { "is_relevant": boolean, "reason": string }
   *
   * @param array $jobData Job information
   * @param string $message User's feedback or additional context
   * @param string $originalStatus Previous status of the job
   * @return string
   */
  public function buildReclassificationPrompt(
    array $jobData,
    string $message,
    string $originalStatus
  ): string {
    $jobDescription = $jobData['description'] ?? '';
    $jobTitle = $jobData['title'] ?? 'Unknown';
    $company = $jobData['company'] ?? 'Unknown';

    $context = "Job Title: {$jobTitle}\nCompany: {$company}\nDescription: {$jobDescription}";
    $promptTemplate = config('prompts.reclassification.prompt');

    return str_replace([
      '{originalStatus}',
      '{message}',
      '{context}'
    ], [
      $originalStatus,
      $message,
      $context
    ], $promptTemplate);
  }
}
