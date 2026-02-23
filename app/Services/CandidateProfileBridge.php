<?php

namespace App\Services;

class CandidateProfileBridge
{
    private array $profile;

    public function __construct()
    {
        $this->profile = app(\App\Services\CandidateProfileService::class)->getProfileAsArray();
    }

    /**
     * Get profile data mapped for curriculum configuration
     */
    public function getMappedProfile(string $lang = 'pt'): array
    {
        if (empty($this->profile)) {
            return $this->getEmptyProfile();
        }

        $isEn = $lang === 'en';

        return [
            'name' => $this->profile['name'] ?? '',
            'subtitle' => $isEn ? 'Backend Developer - 2 years of experience' : 'Desenvolvedor Backend - 2 anos de experiência',
            'age' => $this->profile['age'] ?? ($isEn ? '27 years old' : '27 anos'),
            'marital_status' => $this->profile['marital_status'] ?? ($isEn ? 'married' : 'casado'),
            'location' => $this->profile['location'] ?? ($isEn ? 'Americana, São Paulo, Brazil' : 'Americana, São Paulo, Brasil'),
            'phone' => $this->profile['phone'] ?? '',
            'phone_link' => str_replace(['(', ')', ' ', '-'], '', $this->profile['phone'] ?? ''),
            'email' => $this->profile['email'] ?? '',
            'github' => $this->profile['links']['github'] ?? '',
            'github_display' => str_replace(['https://', 'http://'], '', $this->profile['links']['github'] ?? ''),
            'linkedin' => $this->profile['links']['linkedin'] ?? '',
            'linkedin_display' => str_replace(['https://www.linkedin.com/in/', 'https://linkedin.com/in/'], '', $this->profile['links']['linkedin'] ?? ''),
            'objective' => $isEn ? 'Mid-level Backend Developer' : 'Desenvolvedor Backend Pleno',
            'skills' => $this->mapSkills(),
            'languages' => $this->mapLanguages($isEn),
            'experience' => $this->mapExperience($isEn),
            'education' => $this->mapEducation($isEn),
            'projects' => $this->mapProjects($isEn),
            'certificates' => $this->profile['certifications'] ?? [],
        ];
    }

    private function mapSkills(): array
    {
        if (empty($this->profile['skills'])) return [];

        $skills = [];
        foreach ($this->profile['skills'] as $category => $items) {
            $names = array_map(fn($item) => $item['name'], $items);
            $skills[] = implode(', ', $names);
        }

        return $skills;
    }

    private function mapLanguages(bool $isEn): array
    {
        if (empty($this->profile['languages'])) return [];

        return array_map(function ($lang) use ($isEn) {
            return "{$lang['name']} ({$lang['level']})";
        }, $this->profile['languages']);
    }

    private function mapExperience(bool $isEn): array
    {
        if (empty($this->profile['experience'])) return [];

        return array_map(function ($exp) {
            return [
                'title' => $exp['position'] ?? '',
                'company' => $exp['company'] ?? '',
                'period' => $exp['period'] ?? '',
                'details' => $exp['achievements'] ?? [],
            ];
        }, $this->profile['experience']);
    }

    private function mapEducation(bool $isEn): array
    {
        if (empty($this->profile['education'])) return [];

        return array_map(function ($edu) {
            return [
                'title' => $edu['degree'] ?? '',
                'company' => "{$edu['institution']} — " . ($edu['location'] ?? 'Americana, SP'),
                'period' => $edu['period'] ?? '',
                'details' => [$edu['status'] ?? ''],
            ];
        }, $this->profile['education']);
    }

    private function mapProjects(bool $isEn): array
    {
        // For projects, we might need a specific mapping or just use a placeholder if not in JSON
        // Based on current curriculum.php, projects are hardcoded.
        // I'll keep them as placeholders or see if they are in JSON.
        // In candidate-profile.json, I don't see "projects" yet, but I can add them if needed.
        return [];
    }

    private function getEmptyProfile(): array
    {
        return [
            'name' => '',
            'subtitle' => '',
            'age' => '',
            'marital_status' => '',
            'location' => '',
            'phone' => '',
            'phone_link' => '',
            'email' => '',
            'github' => '',
            'github_display' => '',
            'linkedin' => '',
            'linkedin_display' => '',
            'objective' => '',
            'skills' => [],
            'languages' => [],
            'experience' => [],
            'education' => [],
            'projects' => [],
            'certificates' => [],
        ];
    }
}
