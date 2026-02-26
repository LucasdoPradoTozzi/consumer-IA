<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCandidateProfileRequest;
use App\Http\Requests\UpdateCandidateProfileRequest;
use App\Models\CandidateProfile;

class CandidateProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $profile = CandidateProfile::with([
            'skills.type',
            'experiences.achievements',
            'experiences.skills.type',
            'educations',
            'certifications',
            'languages',
            'locations',
            'contractTypes'
        ])->first();

        return view('candidate-profile.index', compact('profile'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit()
    {
        $profile = CandidateProfile::with([
            'skills.type',
            'experiences.achievements',
            'experiences.skills.type',
            'educations',
            'certifications',
            'languages',
            'locations',
            'contractTypes'
        ])->first() ?? new CandidateProfile();

        $skillTypes = \App\Models\SkillType::all();
        $allSkills = \App\Models\Skill::with('type')->get();
        $allLanguages = \App\Models\Language::all();
        $allLanguageLevels = \App\Models\LanguageLevel::all();

        return view('candidate-profile.edit', compact('profile', 'skillTypes', 'allSkills', 'allLanguages', 'allLanguageLevels'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(\Illuminate\Http\Request $request)
    {

        $profile = CandidateProfile::first() ?? new CandidateProfile();

        $profile->fill($request->only([
            'name',
            'email',
            'phone',
            'summary',
            'seniority',
            'linkedin',
            'github',
            'availability'
        ]));

        $profile->remote = $request->has('remote');
        $profile->hybrid = $request->has('hybrid');
        $profile->onsite = $request->has('onsite');
        $profile->willing_to_relocate = $request->has('willing_to_relocate');

        $profile->save();

        // Sync Skills
        if ($request->filled('skills_json')) {
            $skillsData = json_decode($request->input('skills_json'), true) ?? [];
            $syncData = [];
            foreach ($skillsData as $skillItem) {
                if (!empty($skillItem['skill_id'])) {
                    $syncData[$skillItem['skill_id']] = [
                        'experience_years' => (int)($skillItem['experience_years'] ?? 0),
                    ];
                }
            }
            $profile->skills()->sync($syncData);
        }

        // Sync Experiences
        if ($request->filled('experiences_json')) {
            $experiencesData = json_decode($request->input('experiences_json'), true) ?? [];
            $profile->experiences()->delete();
            foreach ($experiencesData as $expData) {
                if (!empty($expData['company']) || !empty($expData['position'])) {
                    $experience = $profile->experiences()->create([
                        'company' => $expData['company'] ?? '',
                        'position' => $expData['position'] ?? '',
                        'period' => $expData['period'] ?? null,
                        'duration_years' => (float)($expData['duration_years'] ?? 0),
                        'description' => $expData['description'] ?? null,
                    ]);

                    // Sync Achievements (Relational)
                    if (isset($expData['achievements']) && is_array($expData['achievements'])) {
                        foreach ($expData['achievements'] as $achievementText) {
                            if (!empty(trim($achievementText))) {
                                $experience->achievements()->create(['achievement' => trim($achievementText)]);
                            }
                        }
                    }

                    // Sync Skills/Technologies (Pivot)
                    if (isset($expData['skills']) && is_array($expData['skills'])) {
                        $skillIds = array_filter($expData['skills']);
                        $experience->skills()->attach($skillIds);
                    }
                }
            }
        }

        // Sync Educations
        if ($request->filled('educations_json')) {
            $educationsData = json_decode($request->input('educations_json'), true) ?? [];
            $profile->educations()->delete();
            foreach ($educationsData as $edu) {
                if (!empty($edu['institution']) || !empty($edu['degree'])) {
                    $profile->educations()->create([
                        'institution' => $edu['institution'] ?? '',
                        'degree' => $edu['degree'] ?? '',
                        'period' => $edu['period'] ?? null,
                        'status' => $edu['status'] ?? null,
                    ]);
                }
            }
        }

        // Sync Languages
        if ($request->filled('languages_json')) {
            $languagesData = json_decode($request->input('languages_json'), true) ?? [];
            $syncLanguageData = [];
            foreach ($languagesData as $langItem) {
                if (!empty($langItem['language_id']) && !empty($langItem['language_level_id'])) {
                    $syncLanguageData[$langItem['language_id']] = [
                        'language_level_id' => $langItem['language_level_id'],
                    ];
                }
            }
            $profile->languages()->sync($syncLanguageData);
        }

        // Sync Certifications
        if ($request->filled('certifications_json')) {
            $certificationsData = json_decode($request->input('certifications_json'), true) ?? [];
            $profile->certifications()->delete(); // Simple approach: delete and recrease
            foreach ($certificationsData as $cert) {
                if (!empty($cert)) {
                    $profile->certifications()->create(['name' => $cert]);
                }
            }
        }

        // Sync Locations
        if ($request->filled('locations_json')) {
            $locationsData = json_decode($request->input('locations_json'), true) ?? [];
            $profile->locations()->delete();
            foreach ($locationsData as $loc) {
                if (!empty($loc)) {
                    $profile->locations()->create(['location' => $loc]);
                }
            }
        }

        // Sync Contract Types
        if ($request->filled('contract_types_json')) {
            $contractTypesData = json_decode($request->input('contract_types_json'), true) ?? [];
            $profile->contractTypes()->delete();
            foreach ($contractTypesData as $type) {
                if (!empty($type)) {
                    $profile->contractTypes()->create(['type' => $type]);
                }
            }
        }

        return redirect()->route('candidate-profile.index')->with('success', 'Profile updated successfully.');
    }
}
