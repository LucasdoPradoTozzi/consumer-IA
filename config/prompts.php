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
        'prompt' => "You are an AI assistant that scores job-candidate matches.{languageInstruction}
      Job Information:
      - Title: {jobTitle}
      - Required Skills: {skillsText}
      - Description: {jobDescription}

      Candidate Profile:
      - Name: {candidateName}
      - Skills: {candidateSkillsText}
      - Experience: {candidateExperienceText}

      Analyze how well this candidate matches the job requirements and provide a compatibility score from 0 to 100, where:
      - 0-30: Poor match (lacks critical skills or experience)
      - 31-60: Fair match (has some relevant skills but missing key requirements)
      - 61-80: Good match (meets most requirements with minor gaps)
      - 81-100: Excellent match (highly qualified, meets or exceeds all requirements)

      Return your response as a valid JSON object with this exact structure:
      {
        \"score\": 75,
        \"justification\": \"Detailed explanation of the score, highlighting matching skills and gaps\"
      }

      Return ONLY the JSON object, no additional text.",
    ],
    
    'extraction' => [
        'prompt' => "You are an AI assistant that extracts structured information from job postings.
        Job Details:
        - Title: {jobTitle}
        - Company: {company}
        - Description: {jobDescription}

        Extract the following fields from the job posting:
        - Job Title
        - Company Name
        - Job Description
        - Required Skills
        - Location (if available)
        - Salary (if available)
        - Employment Type (e.g., full-time, part-time, contract)
        - Language of the job posting (Portuguese or English)

        Return your response as a valid JSON object with this exact structure:
        {
          \"extracted_info\": {
            \"title\": \"...\",
            \"company\": \"...\",
            \"description\": \"...\",
            \"required_skills\": [\"...\", \"...\"],
            \"location\": \"...\",
            \"salary\": \"...\",
            \"employment_type\": \"...\",
            \"language\": \"portuguese\" or \"english\"
          }
        }

        Return ONLY the JSON object, no additional text.",
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
];
