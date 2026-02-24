<?php

return [
  // Prompt templates for each field
  'cover_letter' => [
    'prompt' => "You are an AI assistant that writes professional cover letters for job applications.
        Job Details:
        - Position: {jobTitle}
        - Company: {company}
        - Description: {jobDescription}

        Candidate Information:
        - Name: {candidateName}
        - Skills: {candidateSkillsText}
        - Experience: {candidateExperience}

        Write a compelling, professional cover letter for this candidate applying to this position. The cover letter should:
        - Be concise (250-350 words)
        - Highlight relevant skills and experience
        - Show enthusiasm for the role and company
        - Be professional but personable
        - Include a strong opening and closing

        Return your response as a valid JSON object with this exact structure:
        {
        \"cover_letter\": \"The complete cover letter text here\"
        }

        Return ONLY the JSON object, no additional text.",
  ],


  'email' => [
    'prompt' => "Você é um assistente de IA que escreve e-mails profissionais para candidaturas de emprego.\n\nDados do Candidato:\n{candidateProfile}\n\nVaga:\n{jobData}\n\nEscreva um assunto e um corpo de e-mail personalizados para esta vaga. O e-mail deve ser profisisonal, curto e despertar o interesse do recrutador. Use o idioma: {language}.\n\nRetorne apenas um JSON com o seguinte formato:\n{\n  \"subject\": \"...\",\n  \"body\": \"...\"\n}",
  ],

  'resume_adjustment' => [
    'prompt' => "Você receberá os dados de um candidato e de uma vaga. Seu objetivo é gerar um JSON de configuração para preencher um template de currículo (pt ou en). O JSON deve conter a chave 'lang' (pt ou en) e todas as variáveis necessárias para preencher o currículo. Responda apenas com o JSON.\n\n[EXEMPLO_DINAMICO]\n\nInformações do Candidato (DADOS REAIS):\n{candidateProfile}\n\nInformações da Vaga (VAGA ALVO):\n{jobData}",
  ],
  'resume_optimization' => [
    'prompt' => "You are an AI assistant that helps optimize resumes for specific job applications.
        Job Requirements:
        - Position: {jobTitle}
        - Description: {jobDescription}
        - Required Skills: {skillsText}

        Current Resume:
        {currentResume}

        Candidate's Skills: {candidateSkills}

        Analyze the resume and suggest adjustments to better align it with the job requirements. Your suggestions should:
        - Highlight relevant experience that matches the job description
        - Emphasize skills mentioned in the job posting
        - Suggest reordering or rephrasing sections for better impact
        - Maintain truthfulness (don't fabricate experience)
        - Keep the same overall length

        Return your response as a valid JSON object with this exact structure:
        {
          \"adjusted_resume\": \"The complete adjusted resume text\",
          \"changes_made\": [
            \"List of specific changes made\",
            \"Each change as a separate string in the array\"
          ]
        }

        Return ONLY the JSON object, no additional text.",
  ],

  'classification' => [
    'prompt' => "You are an AI assistant that analyzes job postings to determine if they are relevant for application.
      Analyze the following job posting and determine if it is a real, legitimate job opportunity that should be considered for application.

      {context}

      Classify this job posting as relevant or not based on these criteria:
      - Is it a real job posting (not spam, scam, or irrelevant content)?
      - Does it have clear job requirements and responsibilities?
      - Is it from a legitimate company or organization?

      Return your response as a valid JSON object with this exact structure:
      {
        \"is_relevant\": true or false,
        \"reason\": \"Brief explanation of your classification decision\"
      }

      Return ONLY the JSON object, no additional text.",
  ],

  'scoring' => [
    'prompt' => "You are an AI assistant specialized in evaluating job-candidate compatibility.

    {languageInstruction}

    Job Information:
    - Title: {jobTitle}
    - Required Skills: {skillsText}
    - Description: {jobDescription}

    Candidate Profile:
    - Name: {candidateName}
    - Skills: {candidateSkillsText}
    - Experience: {candidateExperienceText}

    Evaluation Rules:

    1) Compare job required skills against candidate skills explicitly.
    2) Identify:
      - Matching skills
      - Missing important skills
      - Relevant experience alignment
    3) Weigh technical skills more heavily than soft skills.
    4) Penalize missing critical skills explicitly mentioned in the job description.
    5) Do NOT invent skills or experience.
    6) Base the score strictly on the provided data.

    Scoring Guidelines:
    - 0-30: Lacks most critical skills.
    - 31-60: Partial match but missing important requirements.
    - 61-80: Strong match with minor gaps.
    - 81-100: Excellent alignment with required skills and experience.

    Return a valid JSON object with EXACTLY this structure:

    {
      \"score\": 0,
      \"matched_skills\": [\"...\"],
      \"missing_skills\": [\"...\"],
      \"strengths\": [\"...\"],
      \"gaps\": [\"...\"],
      \"justification\": \"Concise technical explanation of how the score was calculated.\"
    }

    Return ONLY the JSON object.
    Do not include explanations outside JSON.",
  ],

  'extraction' => [
    'prompt' => "You are an AI assistant specialized in extracting structured information from job postings.

      Job Details:
      - Title: {jobTitle}
      - Company: {company}
      - Description: {jobDescription}

      Your task is to extract structured information strictly based on the provided job description.

      Extraction rules:
      - Do NOT invent information.
      - If a field is not explicitly mentioned, return null.
      - Normalize required_skills into a clean array of concise skill keywords.
      - Include only relevant professional skills (technical or core competencies).
      - Detect the language based on the job description text.

      Additionally:
      Provide a 'company_data' object using only reliable prior knowledge.
      - If you are not confident about specific company details, return null for those fields.
      - Do NOT fabricate recent news or unverifiable claims.

      Return a valid JSON object with EXACTLY this structure:

      {
        \"extracted_info\": {
          \"title\": \"...\",
          \"company\": \"...\",
          \"description\": \"...\",
          \"required_skills\": [\"...\", \"...\"],
          \"location\": \"...\",
          \"salary\": \"...\",
          \"employment_type\": \"...\",
          \"language\": \"portuguese\" or \"english\",
          \"company_data\": {
            \"industry\": \"...\",
            \"company_size\": \"...\",
            \"headquarters\": \"...\",
            \"website\": \"...\",
            \"reputation_summary\": \"...\"
          }
        }
      }

      If information is unavailable, use null.
      Return ONLY the JSON object.
      Do not include explanations.",
  ],

  'reclassification' => [
    'prompt' => "You are an AI assistant that analyzes job postings to determine if they are relevant for application.
        This job was previously evaluated with status: \"{originalStatus}\"

        The user has requested reprocessing with this additional context:
        \"{message}\"

        Job details:
        {context}

        Re-evaluate this job posting taking into account the user's message. Consider:
        - The user's specific feedback or concerns
        - Whether the additional context changes the relevance assessment
        - Any new information provided that wasn't considered before
        - The previous status and whether it should be reconsidered

        Return your response as a valid JSON object with this exact structure:
        {
          \"is_relevant\": true or false,
          \"reason\": \"Brief explanation of your classification decision considering the user's message\"
        }

        Return ONLY the JSON object, no additional text.",
  ],

    'unified_application' => [
    'prompt' => "You are an AI assistant specialized in generating strategically tailored job application materials.

      Job Information (jobData):
      {jobData}

      Full Candidate Profile (candidateProfile):
      {candidateProfile}

      Your task is to generate a SINGLE valid JSON object containing:
      - cover_letter
      - email_subject
      - email_body
      - resume_config

      GLOBAL STRATEGY:

      First, internally analyze the job description and identify:
      - Core technical requirements
      - Secondary/bonus skills
      - Main responsibilities
      - Keywords and terminology emphasized

      Do NOT output this analysis.

      Then generate all materials aligned with those priorities.

      IMPORTANT STRUCTURE RULES:

      1) You MUST keep the exact JSON structure and field names as shown in the example.
      2) You MUST keep the same array structure and object keys inside resume_config.
      3) You MUST NOT invent experience, technologies, or achievements not present in candidateProfile.
      4) If something is not supported by candidateProfile, do not fabricate it.

      RESUME ADAPTATION RULES:
      - Prioritize relevant experiences and technologies.
      - Reorder bullet points strategically.
      - Adjust wording to reflect job terminology.
      - Emphasize measurable impact when possible.
      - Keep structure identical to exampleJson.

      COVER LETTER RULES:
      - Strong, direct opening referencing the role.
      - Focus only on the most relevant qualifications.
      - Reinforce alignment with job priorities identified earlier.
      - Do NOT repeat resume bullet points verbatim.
      - Avoid generic phrases.
      - Professional, confident tone.
      - 250–350 words.

      EMAIL RULES:
      - Concise and professional.
      - Reinforce interest and fit in 3–6 sentences.
      - Avoid repeating the full cover letter.
      - Clear subject line aligned with the position.

      The entire output must feel cohesive and strategically aligned.

      Return ONLY a valid JSON.
      Do not include explanations.
      Respond in: {language}

      Here is the REQUIRED JSON structure example (structure must be identical, content must be adapted as described):
      {exampleJson}"
    ]
];
