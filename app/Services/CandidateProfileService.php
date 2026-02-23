<?php

namespace App\Services;

class CandidateProfileService
{
    /**
     * Get the profile data as an array matching the old candidate-profile.json structure.
     * This ensures backwards compatibility with the LLM generation and scoring workers.
     */
    public function getProfileAsArray(): array
    {
        $profile = \App\Models\CandidateProfile::with([
            'skills.type', 
            'experiences.achievements',
            'experiences.skills.type',
            'educations', 'certifications',
            'languages', 'locations', 'contractTypes'
        ])->first();

        if (!$profile) {
            return [];
        }

        $certifications = $profile->certifications ? $profile->certifications->pluck('name')->toArray() : [];
        $locations = $profile->locations ? $profile->locations->pluck('location')->toArray() : [];
        $contractTypes = $profile->contractTypes ? $profile->contractTypes->pluck('type')->toArray() : [];

        $data = [
            'name' => $profile->name ?? '',
            'email' => $profile->email ?? '',
            'phone' => $profile->phone ?? '',
            'summary' => $profile->summary ?? '',
            'seniority' => $profile->seniority ?? '',
            'skills' => [],
            'experience' => [],
            'education' => [],
            'certifications' => $certifications,
            'languages' => [],
            'links' => [
                'github' => $profile->github ?? '',
                'linkedin' => $profile->linkedin ?? '',
            ],
            'preferences' => [
                'remote' => $profile->remote ?? true,
                'hybrid' => $profile->hybrid ?? true,
                'onsite' => $profile->onsite ?? false,
                'availability' => $profile->availability ?? '',
                'willing_to_relocate' => $profile->willing_to_relocate ?? false,
                'locations' => $locations,
                'contract_types' => $contractTypes,
            ]
        ];

        // Format Skills
        if ($profile->skills) {
            foreach ($profile->skills as $skill) {
                $category = $skill->type->name ?? 'other';
                if (!isset($data['skills'][$category])) {
                    $data['skills'][$category] = [];
                }
                $data['skills'][$category][] = [
                    'name' => $skill->name,
                    'level' => $this->yearsToLevel($skill->pivot->experience_years ?? 0),
                    'experience_years' => $skill->pivot->experience_years ?? 0,
                ];
            }
        }

        // Format Experience
        if ($profile->experiences) {
            foreach ($profile->experiences as $exp) {
                $data['experience'][] = [
                    'company' => $exp->company,
                    'position' => $exp->position,
                    'period' => $exp->period,
                    'duration_years' => $exp->duration_years,
                    'description' => $exp->description,
                    'achievements' => $exp->achievements->pluck('achievement')->toArray(),
                    'technologies' => $exp->skills->pluck('name')->toArray(),
                ];
            }
        }

        // Format Education
        if ($profile->educations) {
            foreach ($profile->educations as $edu) {
                $data['education'][] = [
                    'institution' => $edu->institution,
                    'degree' => $edu->degree,
                    'period' => $edu->period,
                    'status' => $edu->status,
                ];
            }
        }

        // Format Languages
        $levels = \App\Models\LanguageLevel::all()->keyBy('id');
        if ($profile->languages) {
            foreach ($profile->languages as $lang) {
                $levelName = $levels[$lang->pivot->language_level_id]->name ?? 'N/A';
                $data['languages'][] = [
                    'name' => $lang->name,
                    'level' => $levelName,
                ];
            }
        }

        return $data;
    }

    private function yearsToLevel(int $years): string
    {
        if ($years < 2) return 'júnior';
        if ($years < 5) return 'pleno';
        return 'sênior';
    }

    /**
     * Output profile as formatted text for LLM prompts.
     */
    public function getResumeText(): string
    {
        $profile = $this->getProfileAsArray();
        if (empty($profile)) {
            return '';
        }

        $text = "{$profile['name']}\n";
        $text .= "{$profile['email']} | {$profile['phone']}\n\n";

        $text .= "SUMMARY:\n{$profile['summary']}\n\n";

        $text .= "SKILLS:\n";
        foreach ($profile['skills'] ?? [] as $category => $items) {
            $text .= ucfirst($category) . ": ";
            $skillNames = array_map(fn($s) => $s['name'] . ' (' . $s['experience_years'] . ' anos)', $items);
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
    }
}
