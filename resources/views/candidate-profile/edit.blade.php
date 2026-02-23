<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Edit Candidate Profile') }}
            </h2>
            <a href="{{ route('candidate-profile.index') }}" class="text-indigo-600 hover:text-indigo-900 transition">
                &larr; Back to Profile
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="POST" action="{{ route('candidate-profile.update') }}">
                @csrf
                @method('PUT')

                <!-- Basic Info -->
                <div class="bg-white p-8 md:p-10 rounded-3xl shadow-sm border border-gray-100 mb-8">
                    <h3 class="text-lg font-bold text-indigo-700 mb-6 border-b pb-2">Basic Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <x-input-label for="name" :value="__('Name')" class="font-semibold text-gray-700 mb-2" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-lg font-bold text-gray-900" :value="old('name', $profile->name)" required autofocus />
                        </div>
                        <div>
                            <x-input-label for="email" :value="__('Email')" class="font-semibold text-gray-700 mb-2" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-lg font-medium text-gray-900" :value="old('email', $profile->email)" required />
                        </div>
                        <div>
                            <x-input-label for="phone" :value="__('Phone')" class="font-semibold text-gray-700 mb-2" />
                            <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-lg font-medium text-gray-900" :value="old('phone', $profile->phone)" />
                        </div>
                        <div>
                            <x-input-label for="seniority" :value="__('Seniority')" class="font-semibold text-gray-700 mb-2" />
                            <select id="seniority" name="seniority" class="border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm mt-1 block w-full bg-gray-50 px-3 py-2 text-lg font-medium text-gray-900">
                                <option value="júnior" {{ old('seniority', $profile->seniority) === 'júnior' ? 'selected' : '' }}>Júnior</option>
                                <option value="pleno" {{ old('seniority', $profile->seniority) === 'pleno' ? 'selected' : '' }}>Pleno</option>
                                <option value="sênior" {{ old('seniority', $profile->seniority) === 'sênior' ? 'selected' : '' }}>Sênior</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-8">
                        <x-input-label for="summary" :value="__('Summary')" class="font-semibold text-gray-700 mb-2" />
                        <textarea id="summary" name="summary" rows="4" class="border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm mt-1 block w-full bg-gray-50 px-3 py-2 text-lg text-gray-700">{{ old('summary', $profile->summary) }}</textarea>
                    </div>
                </div>

                <!-- Links & Preferences -->
                <div class="bg-white p-8 md:p-10 rounded-3xl shadow-sm border border-gray-100 mb-8">
                    <h3 class="text-lg font-bold text-indigo-700 mb-6 border-b pb-2">Links & Preferences</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <x-input-label for="linkedin" :value="__('LinkedIn URL')" class="font-semibold text-gray-700 mb-2" />
                            <x-text-input id="linkedin" name="linkedin" type="url" class="mt-1 block w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-lg font-medium text-gray-900" :value="old('linkedin', $profile->linkedin)" />
                        </div>
                        <div>
                            <x-input-label for="github" :value="__('GitHub URL')" class="font-semibold text-gray-700 mb-2" />
                            <x-text-input id="github" name="github" type="url" class="mt-1 block w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-lg font-medium text-gray-900" :value="old('github', $profile->github)" />
                        </div>
                        <div class="col-span-2 mt-8">
                            <div class="flex flex-wrap items-center gap-6">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="remote" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ old('remote', $profile->remote) ? 'checked' : '' }}>
                                    <span class="ml-2 text-sm text-gray-600">Remote</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="hybrid" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ old('hybrid', $profile->hybrid) ? 'checked' : '' }}>
                                    <span class="ml-2 text-sm text-gray-600">Hybrid</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="onsite" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ old('onsite', $profile->onsite) ? 'checked' : '' }}>
                                    <span class="ml-2 text-sm text-gray-600">On-site</span>
                                </label>
                                <label class="inline-flex items-center ml-8">
                                    <input type="checkbox" name="willing_to_relocate" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ old('willing_to_relocate', $profile->willing_to_relocate) ? 'checked' : '' }}>
                                    <span class="ml-2 text-sm text-gray-600">Willing to Relocate</span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <x-input-label for="availability" :value="__('Availability')" class="font-semibold text-gray-700 mb-2" />
                            <select id="availability" name="availability" class="border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm mt-1 block w-full bg-gray-50 px-3 py-2 text-lg font-medium text-gray-900">
                                <option value="immediate" {{ old('availability', $profile->availability) === 'immediate' ? 'selected' : '' }}>Immediate</option>
                                <option value="15 days" {{ old('availability', $profile->availability) === '15 days' ? 'selected' : '' }}>15 Days</option>
                                <option value="30 days" {{ old('availability', $profile->availability) === '30 days' ? 'selected' : '' }}>30 Days</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Dynamic Sections Using Alpine.js -->
                <div x-data="profileForm()" x-init="init()">
                    <input type="hidden" name="skills_json" :value="JSON.stringify(data.skills)">
                    <input type="hidden" name="experiences_json" :value="JSON.stringify(data.experiences)">
                    <input type="hidden" name="educations_json" :value="JSON.stringify(data.educations)">
                    <input type="hidden" name="languages_json" :value="JSON.stringify(data.languages)">
                    <input type="hidden" name="certifications_json" :value="JSON.stringify(data.certifications)">

                    <!-- Skills -->
                    <div class="bg-white p-6 shadow sm:rounded-lg mb-6">
                        <div class="flex justify-between items-center border-b pb-2 mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Skills</h3>
                            <button type="button" @click="addSkill" class="text-sm bg-indigo-50 text-indigo-700 hover:bg-indigo-100 px-3 py-1 rounded shadow-sm">Add Skill</button>
                        </div>

                        <div class="space-y-4">
                            <template x-for="(skill, index) in data.skills" :key="index">
                                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col md:flex-row gap-4 items-center relative">
                                    <button type="button" @click="removeSkill(index)" class="absolute top-2 right-2 text-red-400 hover:text-red-600 transition" title="Remove Skill">
                                        <i class="bi bi-x-circle-fill text-lg"></i>
                                    </button>
                                    <div class="w-full md:w-3/4">
                                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Skill Selection</label>
                                        <select x-model="skill.skill_id" class="block w-full text-sm font-semibold text-indigo-700 bg-gray-50 rounded-lg border border-gray-200 px-3 py-2">
                                            <option value="">Select a skill...</option>
                                            @foreach($skillTypes as $type)
                                                <optgroup label="{{ $type->name }}">
                                                    @foreach($type->skills as $s)
                                                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="w-full md:w-1/4">
                                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Years</label>
                                        <input x-model="skill.experience_years" type="number" class="block w-full text-sm text-gray-500 bg-gray-50 rounded-lg border border-gray-200 px-3 py-2" placeholder="Years" />
                                    </div>
                                </div>
                            </template>

                            <div x-show="data.skills.length === 0" class="text-center py-6 text-gray-400 italic bg-gray-50 rounded-lg border-2 border-dashed border-gray-100">
                                No skills added yet. Click "Add Skill" to start.
                            </div>
                        </div>
                    </div>

                    <!-- Experience -->
                    <div class="bg-white p-6 shadow sm:rounded-lg mb-6">
                        <div class="flex justify-between items-center border-b pb-2 mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Experience</h3>
                            <button type="button" @click="addExperience" class="text-sm bg-indigo-50 text-indigo-700 hover:bg-indigo-100 px-3 py-1 rounded shadow-sm">Add Experience</button>
                        </div>

                        <div class="space-y-8">
                            <template x-for="(exp, expIdx) in data.experiences" :key="expIdx">
                                <div class="relative pl-8 before:content-[''] before:absolute before:left-0 before:top-2 before:bottom-0 before:w-0.5 before:bg-indigo-50 bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-gray-100">
                                    <div class="absolute left-[-4px] top-2 w-2.5 h-2.5 rounded-full bg-indigo-600 ring-4 ring-white"></div>
                                    <button type="button" @click="removeExperience(expIdx)" class="absolute top-4 right-4 text-red-400 hover:text-red-600 transition" title="Remove Experience">
                                        <i class="bi bi-trash-fill text-lg"></i>
                                    </button>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                        <div>
                                            <input x-model="exp.position" type="text" class="text-xl font-bold text-gray-900 leading-tight bg-transparent border-none focus:ring-0 w-full mb-1 p-0" placeholder="Position" />
                                            <input x-model="exp.company" type="text" class="text-indigo-600 font-bold tracking-tight bg-transparent border-none focus:ring-0 w-full p-0" placeholder="Company Name" />
                                        </div>
                                        <div class="md:text-right">
                                            <input x-model="exp.period" type="text" class="inline-block px-3 py-1 bg-gray-100 rounded-full text-xs font-bold text-gray-500 uppercase tracking-tighter bg-transparent border-none focus:ring-0 text-right p-0" placeholder="Feb 2022 - Present" />
                                            <div class="mt-1 flex items-center md:justify-end gap-1">
                                                <input x-model="exp.duration_years" type="number" step="0.5" class="text-xs text-gray-400 font-medium bg-transparent border-none focus:ring-0 p-0 w-12 text-right" placeholder="Years" />
                                                <span class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Years</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-6">
                                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Description</label>
                                        <textarea x-model="exp.description" rows="3" class="text-gray-600 text-sm leading-relaxed bg-gray-50 rounded-xl p-4 border border-gray-100 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 w-full" placeholder="Describe your responsibilities..."></textarea>
                                    </div>

                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                        <div>
                                            <div class="flex justify-between items-center mb-3">
                                                <label class="block text-[10px] font-bold text-indigo-600 uppercase tracking-widest">Achievements</label>
                                                <button type="button" @click="addAchievement(expIdx)" class="text-[10px] bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full">Add Achievement</button>
                                            </div>
                                            <div class="space-y-2">
                                                <template x-for="(ach, achIdx) in exp.achievements" :key="achIdx">
                                                    <div class="flex items-center gap-2 group">
                                                        <i class="bi bi-check2 text-indigo-500"></i>
                                                        <input x-model="exp.achievements[achIdx]" type="text" class="flex-1 bg-white border border-gray-100 rounded-lg px-3 py-1 text-sm focus:border-indigo-300 transition" />
                                                        <button type="button" @click="removeAchievement(expIdx, achIdx)" class="text-red-300 hover:text-red-500 opacity-0 group-hover:opacity-100">&times;</button>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>

                                        <div>
                                            <div class="flex justify-between items-center mb-3">
                                                <label class="block text-[10px] font-bold text-indigo-600 uppercase tracking-widest">Technologies</label>
                                                <button type="button" @click="addExpSkill(expIdx)" class="text-[10px] bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full">Add Skill</button>
                                            </div>
                                            <div class="flex flex-wrap gap-2">
                                                <template x-for="(sk, skIdx) in exp.skills" :key="skIdx">
                                                    <div class="group relative flex items-center bg-gray-50 rounded-lg px-2 py-1 border border-gray-100 transition">
                                                        <select x-model="exp.skills[skIdx]" class="bg-transparent border-none focus:ring-0 p-0 text-[10px] font-bold text-gray-500 uppercase tracking-widest w-32">
                                                            <option value="">Selection...</option>
                                                            @foreach($skillTypes as $type)
                                                                <optgroup label="{{ $type->name }}">
                                                                    @foreach($type->skills as $s)
                                                                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                                                                    @endforeach
                                                                </optgroup>
                                                            @endforeach
                                                        </select>
                                                        <button type="button" @click="removeExpSkill(expIdx, skIdx)" class="ml-1 text-gray-400 hover:text-red-500 text-xs">
                                                            <i class="bi bi-x"></i>
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Education -->
                    <div class="bg-white p-6 shadow sm:rounded-lg mb-6">
                        <div class="flex justify-between items-center border-b pb-2 mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Education</h3>
                            <button type="button" @click="addEducation" class="text-sm bg-indigo-50 text-indigo-700 hover:bg-indigo-100 px-3 py-1 rounded shadow-sm">Add Education</button>
                        </div>

                        <div class="space_y-4">
                            <template x-for="(edu, index) in data.educations" :key="index">
                                <div class="bg-gray-50/50 p-4 rounded-xl border border-gray-100 relative group">
                                    <button type="button" @click="removeEducation(index)" class="absolute -top-2 -right-2 bg-white text-red-400 hover:text-red-600 border border-gray-100 rounded-full w-6 h-6 flex items-center justify-center shadow-sm opacity-0 group-hover:opacity-100">
                                        <i class="bi bi-x"></i>
                                    </button>
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                        <input x-model="edu.institution" type="text" class="block w-full text-sm bg-white border-gray-200 rounded-lg" placeholder="Institution" />
                                        <input x-model="edu.degree" type="text" class="block w-full text-sm bg-white border-gray-200 rounded-lg" placeholder="Degree" />
                                        <input x-model="edu.period" type="text" class="block w-full text-sm bg-white border-gray-200 rounded-lg" placeholder="Period" />
                                        <input x-model="edu.status" type="text" class="block w-full text-sm bg-white border-gray-200 rounded-lg" placeholder="Status" />
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Languages -->
                    <div class="bg-white p-6 shadow sm:rounded-lg mb-6">
                        <div class="flex justify-between items-center border-b pb-2 mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Languages</h3>
                            <button type="button" @click="addLanguage" class="text-sm bg-indigo-50 text-indigo-700 hover:bg-indigo-100 px-3 py-1 rounded shadow-sm">Add Language</button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <template x-for="(lang, index) in data.languages" :key="index">
                                <div class="bg-gray-50/50 p-4 rounded-xl border border-gray-100 relative group flex gap-4 items-end">
                                    <button type="button" @click="removeLanguage(index)" class="absolute -top-2 -right-2 bg-white text-red-400 hover:text-red-600 border border-gray-100 rounded-full w-6 h-6 flex items-center justify-center shadow-sm opacity-0 group-hover:opacity-100">
                                        <i class="bi bi-x"></i>
                                    </button>
                                    
                                    <div class="flex-1">
                                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Language</label>
                                        <select x-model="lang.language_id" class="block w-full text-sm text-gray-900 bg-white border-gray-200 rounded-lg">
                                            <option value="">Select language...</option>
                                            @foreach($allLanguages as $language)
                                                <option value="{{ $language->id }}">{{ $language->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    
                                    <div class="flex-1">
                                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Proficiency</label>
                                        <select x-model="lang.language_level_id" class="block w-full text-sm text-gray-900 bg-white border-gray-200 rounded-lg">
                                            <option value="">Select level...</option>
                                            @foreach($allLanguageLevels as $level)
                                                <option value="{{ $level->id }}">{{ $level->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div x-show="data.languages.length === 0" class="text-center py-6 text-gray-400 italic">
                            No languages added yet.
                        </div>
                    </div>

                    <!-- Certifications -->
                    <div class="bg-white p-6 shadow sm:rounded-lg mb-6">
                        <div class="flex justify-between items-center border-b pb-2 mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Certifications</h3>
                            <button type="button" @click="addCertification" class="text-sm bg-indigo-50 text-indigo-700 hover:bg-indigo-100 px-3 py-1 rounded shadow-sm">Add Certification</button>
                        </div>

                        <div class="space-y-3">
                            <template x-for="(cert, index) in data.certifications" :key="index">
                                <div class="bg-gray-50/50 p-3 rounded-xl border border-gray-100 relative group flex gap-2">
                                    <button type="button" @click="removeCertification(index)" class="absolute -top-2 -right-2 bg-white text-red-400 hover:text-red-600 border border-gray-100 rounded-full w-6 h-6 flex items-center justify-center shadow-sm opacity-0 group-hover:opacity-100">
                                        <i class="bi bi-x"></i>
                                    </button>
                                    <input x-model="data.certifications[index]" type="text" class="block w-full text-sm bg-white border-gray-200 rounded-lg" placeholder="Certification name" />
                                </div>
                            </template>
                        </div>

                        <div x-show="data.certifications.length === 0" class="text-center py-6 text-gray-400 italic">
                            No certifications added yet.
                        </div>
                    </div>

                    <!-- Save Action -->
                    <div class="flex items-center justify-end">
                        <x-primary-button class="ml-4 px-8 py-3 text-lg">
                            {{ __('Update Profile') }}
                        </x-primary-button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @php
        $alpineData = [
            'skills' => ($profile->skills ?? collect([]))->map(fn($s) => [
                'skill_id' => $s->id,
                'experience_years' => $s->pivot->experience_years ?? 0
            ])->values()->all(),
            'experiences' => ($profile->experiences ?? collect([]))->map(function($e) {
                return [
                    'company' => $e->company,
                    'position' => $e->position,
                    'period' => $e->period,
                    'duration_years' => $e->duration_years,
                    'description' => $e->description,
                    'achievements' => $e->achievements->pluck('achievement')->toArray(),
                    'skills' => $e->skills->pluck('id')->toArray(),
                ];
            })->values()->all(),
            'educations' => ($profile->educations ?? collect([]))->map(fn($e) => $e->only(['institution', 'degree', 'period', 'status']))->values()->all(),
            'languages' => ($profile->languages ?? collect([]))->map(fn($l) => [
                'language_id' => $l->id,
                'language_level_id' => $l->pivot->language_level_id
            ])->values()->all(),
            'certifications' => ($profile->certifications ?? collect([]))->pluck('name')->toArray(),
        ];
    @endphp

    <script>
        function profileForm() {
            return {
                data: @json($alpineData),
                init() {
                    // Initialize if empty
                },
                addSkill() {
                    this.data.skills.push({ skill_id: '', experience_years: 1 });
                },
                removeSkill(index) {
                    this.data.skills.splice(index, 1);
                },
                addExperience() {
                    this.data.experiences.push({
                        company: '', position: '', period: '', duration_years: 1, 
                        description: '', achievements: [''], skills: ['']
                    });
                },
                removeExperience(index) {
                    this.data.experiences.splice(index, 1);
                },
                addAchievement(expIdx) {
                    this.data.experiences[expIdx].achievements.push('');
                },
                removeAchievement(expIdx, achIdx) {
                    this.data.experiences[expIdx].achievements.splice(achIdx, 1);
                },
                addExpSkill(expIdx) {
                    this.data.experiences[expIdx].skills.push('');
                },
                removeExpSkill(expIdx, skIdx) {
                    this.data.experiences[expIdx].skills.splice(skIdx, 1);
                },
                addEducation() {
                    this.data.educations.push({ institution: '', degree: '', period: '', status: '' });
                },
                removeEducation(index) {
                    this.data.educations.splice(index, 1);
                },
                addLanguage() {
                    this.data.languages.push({ language_id: '', language_level_id: '' });
                },
                removeLanguage(index) {
                    this.data.languages.splice(index, 1);
                },
                addCertification() {
                    this.data.certifications.push('');
                },
                removeCertification(index) {
                    this.data.certifications.splice(index, 1);
                }
            }
        }
    </script>
</x-app-layout>