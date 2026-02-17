<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Candidate Profile Configuration
    |--------------------------------------------------------------------------
    |
    | Your candidate profile is loaded from candidate-profile.json in the
    | project root. This allows for structured data with arrays and objects.
    |
    | Edit candidate-profile.json to configure your profile.
    |
    */

    'profile_path' => base_path('candidate-profile.json'),

    /**
     * Load and return the candidate profile from JSON file
     */
    'profile' => function () {
        $path = base_path('candidate-profile.json');

        if (!file_exists($path)) {
            throw new \RuntimeException("Candidate profile not found at: {$path}");
        }

        $json = file_get_contents($path);
        $profile = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON in candidate profile: ' . json_last_error_msg());
        }

        return $profile;
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
        $profile = config('candidate.profile')();

        $text = "{$profile['name']}\n";
        $text .= "{$profile['email']} | {$profile['phone']}\n\n";

        $text .= "SUMMARY:\n{$profile['summary']}\n\n";

        $text .= "SKILLS:\n";
        foreach ($profile['skills'] ?? [] as $category => $items) {
            $text .= ucfirst($category) . ": ";
            $skillNames = array_map(fn($s) => $s['name'], $items);
            $text .= implode(', ', $skillNames) . "\n";
        }

        $text .= "\nEXPERIENCE:\n";
        foreach ($profile['experience'] ?? [] as $exp) {
            $text .= "{$exp['position']} at {$exp['company']} ({$exp['period']})\n";
            $text .= "{$exp['description']}\n";
            foreach ($exp['achievements'] ?? [] as $achievement) {
                $text .= "- {$achievement}\n";
            }
            $text .= "\n";
        }

        $text .= "EDUCATION:\n";
        foreach ($profile['education'] ?? [] as $edu) {
            $text .= "{$edu['degree']} at {$edu['institution']} ({$edu['period']}) - {$edu['status']}\n";
        }

        if (!empty($profile['certifications'])) {
            $text .= "\nCERTIFICATIONS:\n";
            foreach ($profile['certifications'] as $cert) {
                $text .= "- {$cert}\n";
            }
        }

        if (!empty($profile['languages'])) {
            $text .= "\nLANGUAGES:\n";
            foreach ($profile['languages'] as $lang) {
                $text .= "- {$lang['name']}: {$lang['level']}\n";
            }
        }

        return $text;
    },

];
