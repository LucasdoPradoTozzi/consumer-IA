<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Candidate Profile Settings
    |--------------------------------------------------------------------------
    |
    | Your candidate profile is now managed via the database and the web interface.
    | The CandidateProfileService accesses these settings on the fly.
    |
    */

    /**
     * Load and return the candidate profile from the new Service
     */
    'profile' => function () {
        return app(\App\Services\CandidateProfileService::class)->getProfileAsArray();
    },

    /**
     * Get all skills as flat array (for simple matching)
     */
    'skills_flat' => function () {
        $profile = config('candidate.profile')();
        $skills = [];

        foreach ($profile['skills'] ?? [] as $category => $items) {
            foreach ($items as $skill) {
                $skills[] = $skill['name'];
            }
        }

        return $skills;
    },

    /**
     * Get skills by level
     */
    'skills_by_level' => function (string $level) {
        $profile = config('candidate.profile')();
        $skills = [];

        foreach ($profile['skills'] ?? [] as $category => $items) {
            foreach ($items as $skill) {
                if (strtolower($skill['level'] ?? '') === strtolower($level)) {
                    $skills[] = $skill['name'];
                }
            }
        }

        return $skills;
    },

    /**
     * Get total years of experience
     */
    'total_experience_years' => function () {
        $profile = config('candidate.profile')();
        $total = 0;

        foreach ($profile['experience'] ?? [] as $exp) {
            $total += $exp['duration_years'] ?? 0;
        }

        return $total;
    },

    /**
     * Get resume text formatted for LLM
     */
    'resume_text' => function () {
        return app(\App\Services\CandidateProfileService::class)->getResumeText();
    },

];
