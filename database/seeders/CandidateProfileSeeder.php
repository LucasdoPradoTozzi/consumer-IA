<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\SkillType;
use App\Models\Skill;
use App\Models\CandidateProfile;

class CandidateProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = base_path('candidate-profile.json');

        if (!file_exists($path)) {
            $this->command->warn("candidate-profile.json not found. Skipping profile seeding.");
            return;
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);

        if (!$data) {
            $this->command->error("Invalid JSON in candidate-profile.json.");
            return;
        }

        DB::transaction(function () use ($data) {
            $preferences = $data['preferences'] ?? [];
            $links = $data['links'] ?? [];

            $profile = CandidateProfile::create([
                'name' => $data['name'] ?? 'Unknown',
                'email' => $data['email'] ?? 'unknown@example.com',
                'phone' => $data['phone'] ?? null,
                'summary' => $data['summary'] ?? null,
                'remote' => $preferences['remote'] ?? true,
                'hybrid' => $preferences['hybrid'] ?? true,
                'onsite' => $preferences['onsite'] ?? false,
                'availability' => $preferences['availability'] ?? 'immediate',
                'willing_to_relocate' => $preferences['willing_to_relocate'] ?? false,
                'github' => $links['github'] ?? null,
                'linkedin' => $links['linkedin'] ?? null,
                'seniority' => $data['seniority'] ?? 'pleno',
            ]);

            // 1. Skills & Skill Types
            $skillsData = $data['skills'] ?? [];
            foreach ($skillsData as $category => $skills) {
                $skillType = SkillType::firstOrCreate(['name' => $category]);
                
                foreach ($skills as $skillData) {
                    $skill = Skill::firstOrCreate([
                        'skill_type_id' => $skillType->id,
                        'name' => $skillData['name']
                    ]);

                    $profile->skills()->attach($skill->id, [
                        'experience_years' => 2, // Default heuristic
                    ]);
                }
            }

            // 2. Experience, Achievements & Experience Skills
            $experiences = $data['experience'] ?? [];
            foreach ($experiences as $exp) {
                $experience = $profile->experiences()->create([
                    'company' => $exp['company'] ?? '',
                    'position' => $exp['position'] ?? '',
                    'period' => $exp['period'] ?? null,
                    'duration_years' => $exp['duration_years'] ?? 0,
                    'description' => $exp['description'] ?? null,
                ]);

                // Achievements
                $achievements = $exp['achievements'] ?? [];
                foreach ($achievements as $achievementText) {
                    $experience->achievements()->create([
                        'achievement' => $achievementText
                    ]);
                }

                // Experience Skills (Technologies)
                $technologies = $exp['technologies'] ?? [];
                foreach ($technologies as $techName) {
                    // Try to find if this skill already exists (maybe in a generic 'Other' category if not found)
                    $skill = Skill::where('name', 'ilike', $techName)->first();
                    
                    if (!$skill) {
                        $otherType = SkillType::firstOrCreate(['name' => 'Other']);
                        $skill = Skill::create([
                            'skill_type_id' => $otherType->id,
                            'name' => $techName
                        ]);
                    }

                    $experience->skills()->attach($skill->id);
                }
            }

            // 3. Education
            $educations = $data['education'] ?? [];
            foreach ($educations as $edu) {
                $profile->educations()->create([
                    'institution' => $edu['institution'] ?? '',
                    'degree' => $edu['degree'] ?? '',
                    'period' => $edu['period'] ?? null,
                    'status' => $edu['status'] ?? null,
                ]);
            }

            // 4. Certifications
            $certifications = $data['certifications'] ?? [];
            foreach ($certifications as $cert) {
                $profile->certifications()->create([
                    'name' => is_string($cert) ? $cert : ($cert['name'] ?? ''),
                ]);
            }

            // 5. Languages
            $languagesData = $data['languages'] ?? [];
            $syncLanguages = [];
            foreach ($languagesData as $lang) {
                $language = \App\Models\Language::where('name', 'ilike', $lang['name'] ?? '')->first();
                $level = \App\Models\LanguageLevel::where('name', 'ilike', $lang['level'] ?? '')->first();
                
                if (!$language && !empty($lang['name'])) {
                    $language = \App\Models\Language::create(['name' => $lang['name']]);
                }
                
                if (!$level && !empty($lang['level'])) {
                    $level = \App\Models\LanguageLevel::where('name', 'ilike', 'Básico')->first(); // Fallback
                }
                
                if ($language && $level) {
                    $syncLanguages[$language->id] = ['language_level_id' => $level->id];
                }
            }
            $profile->languages()->sync($syncLanguages);

            // 6. Locations
            $locations = $preferences['locations'] ?? [];
            foreach ($locations as $loc) {
                $profile->locations()->create([
                    'location' => $loc,
                ]);
            }

            // 7. Contract Types
            $contractTypes = $preferences['contract_types'] ?? [];
            foreach ($contractTypes as $type) {
                $profile->contractTypes()->create([
                    'type' => $type,
                ]);
            }

            $this->command->info("Candidate Profile imported with new relational structure!");
        });
    }
}
