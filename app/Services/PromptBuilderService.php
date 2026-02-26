<?php

namespace App\Services;

use Exception;

class PromptBuilderService
{
  /**
   * Build analyze prompt — extracts job information and scores candidate in one LLM call.
   * Returns JSON with: { "extracted_info": object, "scoring": object }
   *
   * @param array $jobData Job information
   * @param array $candidateProfile Candidate profile data
   * @return string
   */
  public function buildAnalyzePrompt(array $jobData, array $candidateProfile): string
  {
    $jobTitle = $jobData['title'] ?? 'Unknown';
    $company  = $jobData['company'] ?? 'Unknown';
    $jobDescription = $jobData['description'] ?? '';

    $candidateProfileJson = json_encode($candidateProfile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    $promptTemplate = config('prompts.analyze.prompt');

    return str_replace([
      '{jobTitle}',
      '{company}',
      '{jobDescription}',
      '{candidateProfile}',
    ], [
      $jobTitle,
      $company,
      $jobDescription,
      $candidateProfileJson,
    ], $promptTemplate);
  }

  /**
   * Build unified prompt for application materials (cover letter, email, resume) in a single LLM call
   * Returns JSON with: { "cover_letter": string, "email_subject": string, "email_body": string, "resume_config": object }
   * @param array $jobData
   * @param array $candidateProfile
   * @param string|null $language
   * @return string
   */
  public function buildUnifiedApplicationPrompt(array $jobData, array $candidateProfile, ?string $language = null): string
  {
    $examplePt = config('curriculum.default_candidate');
    $exampleEn = config('curriculum_en.default_candidate');

    $examplePtJson = json_encode(['lang' => 'pt'] + $examplePt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $exampleEnJson = json_encode(['lang' => 'en'] + $exampleEn, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // Detect language
    $lang = strtolower($language ?? 'pt');
    if ($lang === 'portuguese') $lang = 'pt';
    if ($lang === 'english') $lang = 'en';
    if ($lang !== 'en') $lang = 'pt';

    $jobDataJson = json_encode($jobData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $candidateProfileJson = json_encode($candidateProfile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    $exampleJson = $lang === 'en' ? $exampleEnJson : $examplePtJson;
    $template = $lang === 'en' ? 'en' : 'default';
    $languageLabel = $lang === 'en' ? 'Inglês' : 'Português';

    $promptTemplate = config('prompts.unified_application.prompt');
    $prompt = str_replace([
      '{jobData}',
      '{candidateProfile}',
      '{template}',
      '{language}',
      '{exampleJson}'
    ], [
      $jobDataJson,
      $candidateProfileJson,
      $template,
      $languageLabel,
      $exampleJson
    ], $promptTemplate);

    \Log::info('[PromptBuilder] Final prompt generated', [
      'prompt' => mb_substr($prompt, 0, 1000) . (strlen($prompt) > 1000 ? '... (truncated)' : ''),
    ]);

    return $prompt;
  }
}
