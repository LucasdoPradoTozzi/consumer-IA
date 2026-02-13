<?php

namespace App\Services;

class PromptBuilderService
{
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

    return <<<PROMPT
You are an AI assistant that analyzes job postings to determine if they are relevant for application.

Analyze the following job posting and determine if it is a real, legitimate job opportunity that should be considered for application.

{$context}

Classify this job posting as relevant or not based on these criteria:
- Is it a real job posting (not spam, scam, or irrelevant content)?
- Does it have clear job requirements and responsibilities?
- Is it from a legitimate company or organization?

Return your response as a valid JSON object with this exact structure:
{
  "is_relevant": true or false,
  "reason": "Brief explanation of your classification decision"
}

Return ONLY the JSON object, no additional text.
PROMPT;
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

    $candidateName = $candidateProfile['name'] ?? 'Candidate';
    $candidateSkills = $candidateProfile['skills'] ?? [];
    $candidateExperience = $candidateProfile['experience'] ?? '';

    $skillsText = empty($requiredSkills) ? 'Not specified' : implode(', ', $requiredSkills);
    $candidateSkillsText = empty($candidateSkills) ? 'Not specified' : implode(', ', $candidateSkills);

    return <<<PROMPT
You are an AI assistant that scores job-candidate matches.

Job Information:
- Title: {$jobTitle}
- Required Skills: {$skillsText}
- Description: {$jobDescription}

Candidate Profile:
- Name: {$candidateName}
- Skills: {$candidateSkillsText}
- Experience: {$candidateExperience}

Analyze how well this candidate matches the job requirements and provide a compatibility score from 0 to 100, where:
- 0-30: Poor match (lacks critical skills or experience)
- 31-60: Fair match (has some relevant skills but missing key requirements)
- 61-80: Good match (meets most requirements with minor gaps)
- 81-100: Excellent match (highly qualified, meets or exceeds all requirements)

Return your response as a valid JSON object with this exact structure:
{
  "score": 75,
  "justification": "Detailed explanation of the score, highlighting matching skills and gaps"
}

Return ONLY the JSON object, no additional text.
PROMPT;
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
    $jobTitle = $jobData['title'] ?? 'Unknown';
    $company = $jobData['company'] ?? 'Unknown Company';
    $jobDescription = $jobData['description'] ?? '';

    $candidateName = $candidateProfile['name'] ?? 'Candidate';
    $candidateSkills = $candidateProfile['skills'] ?? [];
    $candidateExperience = $candidateProfile['experience'] ?? '';

    $candidateSkillsText = empty($candidateSkills) ? 'Various skills' : implode(', ', $candidateSkills);

    return <<<PROMPT
You are an AI assistant that writes professional cover letters for job applications.

Job Details:
- Position: {$jobTitle}
- Company: {$company}
- Description: {$jobDescription}

Candidate Information:
- Name: {$candidateName}
- Skills: {$candidateSkillsText}
- Experience: {$candidateExperience}

Write a compelling, professional cover letter for this candidate applying to this position. The cover letter should:
- Be concise (250-350 words)
- Highlight relevant skills and experience
- Show enthusiasm for the role and company
- Be professional but personable
- Include a strong opening and closing

Return your response as a valid JSON object with this exact structure:
{
  "cover_letter": "The complete cover letter text here"
}

Return ONLY the JSON object, no additional text.
PROMPT;
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
    $jobTitle = $jobData['title'] ?? 'Unknown';
    $jobDescription = $jobData['description'] ?? '';
    $requiredSkills = $jobData['required_skills'] ?? [];

    $currentResume = $candidateProfile['resume_text'] ?? '';
    $candidateSkills = $candidateProfile['skills'] ?? [];

    $skillsText = empty($requiredSkills) ? 'Not specified' : implode(', ', $requiredSkills);

    return <<<PROMPT
You are an AI assistant that helps optimize resumes for specific job applications.

Job Requirements:
- Position: {$jobTitle}
- Description: {$jobDescription}
- Required Skills: {$skillsText}

Current Resume:
{$currentResume}

Candidate's Skills: {$candidateSkills}

Analyze the resume and suggest adjustments to better align it with the job requirements. Your suggestions should:
- Highlight relevant experience that matches the job description
- Emphasize skills mentioned in the job posting
- Suggest reordering or rephrasing sections for better impact
- Maintain truthfulness (don't fabricate experience)
- Keep the same overall length

Return your response as a valid JSON object with this exact structure:
{
  "adjusted_resume": "The complete adjusted resume text",
  "changes_made": [
    "List of specific changes made",
    "Each change as a separate string in the array"
  ]
}

Return ONLY the JSON object, no additional text.
PROMPT;
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

    return <<<PROMPT
You are an AI assistant that analyzes job postings to determine if they are relevant for application.

This job was previously evaluated with status: "{$originalStatus}"

The user has requested reprocessing with this additional context:
"{$message}"

Job details:
{$context}

Re-evaluate this job posting taking into account the user's message. Consider:
- The user's specific feedback or concerns
- Whether the additional context changes the relevance assessment
- Any new information provided that wasn't considered before
- The previous status and whether it should be reconsidered

Return your response as a valid JSON object with this exact structure:
{
  "is_relevant": true or false,
  "reason": "Brief explanation of your classification decision considering the user's message"
}

Return ONLY the JSON object, no additional text.
PROMPT;
  }
}
