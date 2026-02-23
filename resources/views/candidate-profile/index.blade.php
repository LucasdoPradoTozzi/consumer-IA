<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h2 class="font-bold text-2xl text-gray-900 tracking-tight">
                    {{ __('Professional Profile') }}
                </h2>
                <p class="text-sm text-gray-500">Manage your candidate information for better application matching.</p>
            </div>
            @if($profile)
                <div class="flex gap-2">
                    <a href="{{ route('candidate-profile.edit') }}"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm">
                        <i class="bi bi-pencil-square mr-2"></i> Edit Profile
                    </a>
                </div>
            @endif
        </div>
    </x-slot>

    @if(!$profile)
        <div class="py-12">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white/70 backdrop-blur-md overflow-hidden shadow-xl sm:rounded-2xl border border-white/20 p-12 text-center">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-indigo-100 text-indigo-600 rounded-full mb-6">
                        <i class="bi bi-person-badge text-3xl"></i>
                    </div>
                    <h2 class="text-3xl font-extrabold text-gray-900 mb-4">No Profile Found</h2>
                    <p class="text-lg text-gray-600 mb-8 max-w-md mx-auto">
                        Your profile is essential for our AI to tailor your applications and find the best job matches for your skills and experience.
                    </p>
                    <a href="{{ route('candidate-profile.edit') }}"
                        class="inline-flex items-center px-8 py-3 bg-indigo-600 border border-transparent rounded-xl font-bold text-lg text-white hover:bg-indigo-700 transition-all shadow-lg hover:shadow-indigo-200">
                        <i class="bi bi-plus-circle mr-2"></i> Create Your Profile
                    </a>
                </div>
            </div>
        </div>
    @else
        <div class="py-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

                @if (session('success'))
                    <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded-r-lg shadow-sm" role="alert">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="bi bi-check-circle-fill text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-700 font-medium">
                                    {{ session('success') }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Hero Section / Personal Info -->
                <div class="relative overflow-hidden bg-white shadow-xl sm:rounded-3xl border border-gray-100">
                    <!-- Background Decoration -->
                    <div class="absolute top-0 right-0 -m-10 w-64 h-64 bg-indigo-50 rounded-full blur-3xl opacity-50"></div>
                    <div class="absolute bottom-0 left-0 -m-10 w-48 h-48 bg-purple-50 rounded-full blur-3xl opacity-50"></div>

                    <div class="relative p-8 md:p-10 flex flex-col md:flex-row gap-8 items-center md:items-start text-center md:text-left">
                        <div class="flex-shrink-0">
                            <div class="w-32 h-32 bg-gray-100 rounded-2xl flex items-center justify-center text-indigo-600 border-4 border-white shadow-lg overflow-hidden">
                                @if(false) {{-- Future: profile picture --}}
                                @else
                                    <span class="text-4xl font-bold uppercase">{{ substr($profile->name, 0, 1) }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex-grow">
                            <div class="flex flex-col md:flex-row md:items-baseline gap-2 md:gap-4 mb-4">
                                <h1 class="text-4xl font-black text-gray-900 tracking-tight">{{ $profile->name }}</h1>
                                <span class="px-3 py-1 bg-indigo-100 text-indigo-700 text-xs font-bold uppercase tracking-wider rounded-full">{{ $profile->seniority ?? 'Professional' }}</span>
                            </div>

                            <p class="text-lg text-gray-600 mb-6 leading-relaxed max-w-4xl italic">
                                "{{ $profile->summary }}"
                            </p>

                            <div class="flex flex-wrap gap-y-3 gap-x-6">
                                <div class="flex items-center text-sm text-gray-500">
                                    <i class="bi bi-envelope mr-2 text-indigo-500"></i> {{ $profile->email }}
                                </div>
                                @if($profile->phone)
                                    <div class="flex items-center text-sm text-gray-500">
                                        <i class="bi bi-telephone mr-2 text-indigo-500"></i> {{ $profile->phone }}
                                    </div>
                                @endif
                                @if($profile->linkedin)
                                    <a href="{{ $profile->linkedin }}" target="_blank" class="flex items-center text-sm text-indigo-600 hover:text-indigo-800 transition">
                                        <i class="bi bi-linkedin mr-2"></i> LinkedIn
                                    </a>
                                @endif
                                @if($profile->github)
                                    <a href="{{ $profile->github }}" target="_blank" class="flex items-center text-sm text-gray-700 hover:text-black transition">
                                        <i class="bi bi-github mr-2"></i> GitHub
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Left Column: Preferences & Skills -->
                    <div class="lg:col-span-1 space-y-8">
                        <!-- Preferences Card -->
                        <div class="bg-gray-50/50 backdrop-blur-sm p-6 rounded-3xl border border-gray-100 shadow-sm">
                            <h3 class="text-xs font-black text-indigo-600 uppercase tracking-widest mb-4 flex items-center">
                                <i class="bi bi-sliders mr-2"></i> Work Preferences
                            </h3>
                            <div class="space-y-4">
                                <div>
                                    <p class="text-xs text-gray-400 font-bold uppercase tracking-tighter mb-1">Work Setup</p>
                                    <div class="flex flex-wrap gap-2">
                                        @if($profile->remote) <span class="px-2 py-1 bg-green-100 text-green-700 text-[10px] font-bold rounded">REMOTE</span> @endif
                                        @if($profile->hybrid) <span class="px-2 py-1 bg-blue-100 text-blue-700 text-[10px] font-bold rounded">HYBRID</span> @endif
                                        @if($profile->onsite) <span class="px-2 py-1 bg-orange-100 text-orange-700 text-[10px] font-bold rounded">ON-SITE</span> @endif
                                    </div>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400 font-bold uppercase tracking-tighter mb-1">Availability</p>
                                    <p class="text-sm font-semibold text-gray-800 capitalize">{{ $profile->availability ?? 'Not specified' }}</p>
                                </div>
                                @if($profile->locations && $profile->locations->count() > 0)
                                    <div>
                                        <p class="text-xs text-gray-400 font-bold uppercase tracking-tighter mb-1">Preferred Locations</p>
                                        <p class="text-sm font-semibold text-gray-800">{{ $profile->locations->pluck('location')->join(', ') }}</p>
                                    </div>
                                @endif
                                @if($profile->contractTypes && $profile->contractTypes->count() > 0)
                                    <div>
                                        <p class="text-xs text-gray-400 font-bold uppercase tracking-tighter mb-1">Desired Contract</p>
                                        <p class="text-sm font-semibold text-gray-800 uppercase">{{ $profile->contractTypes->pluck('type')->join(', ') }}</p>
                                    </div>
                                @endif
                                <div>
                                    <p class="text-xs text-gray-400 font-bold uppercase tracking-tighter mb-1">Willing to Relocate</p>
                                    <p class="text-sm font-semibold text-gray-800">{{ $profile->willing_to_relocate ? 'Yes' : 'No' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Skills Card -->
                        <div class="bg-indigo-900 text-white p-8 rounded-3xl shadow-xl shadow-indigo-100">
                            <h3 class="text-xs font-black text-indigo-300 uppercase tracking-widest mb-6">
                                <i class="bi bi-lightning-charge-fill mr-1"></i> Technical Skills
                            </h3>
                            
                            @if($profile->skills && $profile->skills->count() > 0)
                                @php $groupedSkills = $profile->skills->groupBy(fn($skill) => $skill->type->name ?? 'Other'); @endphp
                                <div class="space-y-6">
                                    @foreach($groupedSkills as $category => $skills)
                                        <div>
                                            <h4 class="text-sm font-bold text-white mb-3 border-b border-indigo-800 pb-1 capitalize">{{ $category }}</h4>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($skills as $skill)
                                                    <div class="group relative">
                                                        <span class="inline-block px-3 py-1 bg-white/10 hover:bg-white/20 rounded-lg text-xs font-medium transition cursor-default">
                                                            {{ $skill->name }}
                                                            <span class="ml-1 opacity-50 text-[10px]">{{ $skill->pivot->experience_years }}y</span>
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-indigo-400 italic">No skills listed yet.</p>
                            @endif
                        </div>
                    </div>

                    <!-- Right Column: Experience, Education, etc. -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Experience Section -->
                        <div class="bg-white p-8 md:p-10 rounded-3xl shadow-sm border border-gray-100">
                            <h3 class="text-2xl font-black text-gray-900 mb-8 border-b border-gray-100 pb-4 flex items-center">
                                <i class="bi bi-briefcase mr-3 text-indigo-600"></i> Professional Experience
                            </h3>

                            @if($profile->experiences && $profile->experiences->count() > 0)
                                <div class="space-y-12">
                                    @foreach($profile->experiences as $exp)
                                        <div class="relative pl-8 before:content-[''] before:absolute before:left-0 before:top-2 before:bottom-0 before:w-0.5 before:bg-gray-100">
                                            <!-- Timeline Dot -->
                                            <div class="absolute left-[-4px] top-2 w-2.5 h-2.5 rounded-full bg-indigo-600 ring-4 ring-white"></div>
                                            
                                            <div class="flex flex-col md:flex-row justify-between items-start mb-4">
                                                <div>
                                                    <h4 class="text-xl font-bold text-gray-900 leading-tight">{{ $exp->position }}</h4>
                                                    <p class="text-indigo-600 font-bold tracking-tight">{{ $exp->company }}</p>
                                                </div>
                                                <div class="mt-2 md:mt-0 md:text-right">
                                                    <span class="inline-block px-3 py-1 bg-gray-100 rounded-full text-xs font-bold text-gray-500 uppercase tracking-tighter">{{ $exp->period }}</span>
                                                    @if($exp->duration_years)
                                                        <p class="text-xs text-gray-400 mt-1 font-medium">{{ $exp->duration_years }} years</p>
                                                    @endif
                                                </div>
                                            </div>

                                            @if($exp->description)
                                                <div class="text-gray-600 text-sm leading-relaxed mb-4">
                                                    {{ $exp->description }}
                                                </div>
                                            @endif

                                            @if($exp->achievements && $exp->achievements->count() > 0)
                                                <div class="mb-4">
                                                    <ul class="list-none space-y-2">
                                                        @foreach($exp->achievements as $ach)
                                                            <li class="flex items-start text-sm text-gray-700">
                                                                  <i class="bi bi-arrow-right-short text-indigo-500 mr-1 mt-0.5"></i>
                                                                  {{ $ach->achievement }}
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif

                                            @if($exp->skills && $exp->skills->count() > 0)
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach($exp->skills as $skill)
                                                        <span class="text-[10px] font-black tracking-widest uppercase px-2 py-0.5 bg-gray-50 text-gray-500 rounded border border-gray-100">
                                                            {{ $skill->name }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="bg-gray-50 rounded-2xl p-8 text-center text-gray-400 italic">
                                    No professional experience recorded.
                                </div>
                            @endif
                        </div>

                        <!-- Education & Others -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                             <!-- Education -->
                            <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100">
                                <h3 class="text-lg font-black text-gray-900 mb-6 flex items-center">
                                    <i class="bi bi-mortarboard mr-2 text-indigo-600"></i> Education
                                </h3>
                                @if($profile->educations && $profile->educations->count() > 0)
                                    <div class="space-y-6">
                                        @foreach($profile->educations as $edu)
                                            <div class="group">
                                                <p class="text-sm font-bold text-gray-900 group-hover:text-indigo-600 transition">{{ $edu->degree }}</p>
                                                <p class="text-xs text-gray-500">{{ $edu->institution }}</p>
                                                <div class="flex justify-between items-center mt-2">
                                                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ $edu->period }}</span>
                                                    <span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-[10px] font-bold rounded">{{ $edu->status }}</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-xs text-gray-400 italic">No education provided.</p>
                                @endif
                            </div>

                            <!-- Languages & Certs -->
                            <div class="space-y-8">
                                <!-- Languages -->
                                <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100">
                                    <h3 class="text-sm font-black text-gray-900 mb-4 flex items-center uppercase tracking-widest">
                                        <i class="bi bi-translate mr-2 text-indigo-600"></i> Languages
                                    </h3>
                                    @php $allLevels = \App\Models\LanguageLevel::all()->keyBy('id'); @endphp
                                    @if($profile->languages && $profile->languages->count() > 0)
                                        <div class="grid grid-cols-1 gap-3">
                                            @foreach($profile->languages as $lang)
                                                <div class="flex justify-between items-center p-2 bg-gray-50 rounded-xl">
                                                    <span class="text-xs font-bold text-gray-700 leading-none">{{ $lang->name }}</span>
                                                    @php $levelName = $allLevels[$lang->pivot->language_level_id]->name ?? 'N/A'; @endphp
                                                    <span class="px-2 py-1 bg-white text-indigo-600 text-[10px] font-black rounded-lg shadow-sm">{{ $levelName }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-xs text-gray-400 italic">No languages specified.</p>
                                    @endif
                                </div>

                                <!-- Certifications -->
                                <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100">
                                    <h3 class="text-sm font-black text-gray-900 mb-4 flex items-center uppercase tracking-widest">
                                        <i class="bi bi-award mr-2 text-indigo-600"></i> Certifications
                                    </h3>
                                    @if($profile->certifications && $profile->certifications->count() > 0)
                                        <div class="space-y-3">
                                            @foreach($profile->certifications as $cert)
                                                <div class="flex items-center text-xs text-gray-600">
                                                    <i class="bi bi-patch-check-fill text-indigo-400 mr-2"></i>
                                                    {{ $cert->name }}
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-xs text-gray-400 italic">No certifications listed.</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Final Call to Action -->
                        <div class="flex flex-col items-center justify-center pt-8 border-t border-gray-100">
                            <p class="text-sm text-gray-400 font-medium mb-4 uppercase tracking-tighter">Need to update your professional story?</p>
                            <a href="{{ route('candidate-profile.edit') }}" 
                                class="inline-flex items-center px-10 py-4 bg-gray-900 text-white rounded-2xl font-black text-lg hover:bg-black transition-all shadow-xl hover:shadow-gray-200 group">
                                <i class="bi bi-pencil-square mr-3 text-indigo-400 group-hover:scale-110 transition"></i> Edit Your Complete Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-app-layout>