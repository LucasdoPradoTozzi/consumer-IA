<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Manage Skills & Technologies') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            
            <!-- Skill Types Management -->
            <div class="bg-white p-8 md:p-10 rounded-3xl shadow-sm border border-gray-100">
                <h3 class="text-lg font-bold text-indigo-700 mb-6 border-b pb-2">Skill Categories</h3>
                
                <form action="{{ route('skills.types.store') }}" method="POST" class="mb-8 flex gap-4">
                    @csrf
                    <div class="flex-1">
                        <x-text-input name="name" type="text" placeholder="New Category Name (e.g. Backend)" class="w-full" required />
                    </div>
                    <x-primary-button>Add Category</x-primary-button>
                </form>

                <div class="flex flex-wrap gap-3">
                    @foreach($skillTypes as $type)
                        <div class="flex items-center gap-2 bg-indigo-50 text-indigo-700 px-4 py-2 rounded-full border border-indigo-100">
                            <span class="font-bold text-sm">{{ $type->name }}</span>
                            <form action="{{ route('skills.types.destroy', $type) }}" method="POST" onsubmit="return confirm('Delete this category? Only possible if empty.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="hover:text-red-500 transition">
                                    <i class="bi bi-x-circle-fill"></i>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Skills Management -->
            <div class="bg-white p-8 md:p-10 rounded-3xl shadow-sm border border-gray-100">
                <h3 class="text-lg font-bold text-indigo-700 mb-6 border-b pb-2">Skills & Technologies</h3>

                <form action="{{ route('skills.store') }}" method="POST" class="mb-10 grid grid-cols-1 md:grid-cols-3 gap-4 bg-gray-50 p-6 rounded-2xl border border-gray-100">
                    @csrf
                    <div class="md:col-span-1">
                        <x-input-label value="Category" class="mb-2" />
                        <select name="skill_type_id" class="w-full border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm" required>
                            <option value="">Select Category</option>
                            @foreach($skillTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-1">
                        <x-input-label value="Skill/Tech Name" class="mb-2" />
                        <x-text-input name="name" type="text" placeholder="e.g. Laravel" class="w-full" required />
                    </div>
                    <div class="flex items-end">
                        <x-primary-button class="w-full justify-center">Add Skill</x-primary-button>
                    </div>
                </form>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($skillTypes as $type)
                        <div class="p-6 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md transition">
                            <h4 class="text-indigo-600 font-black uppercase tracking-widest text-xs mb-4 border-b pb-2">{{ $type->name }}</h4>
                            <div class="space-y-2">
                                @forelse($type->skills as $skill)
                                    <div class="flex justify-between items-center group">
                                        <span class="text-sm font-medium text-gray-700">{{ $skill->name }}</span>
                                        <form action="{{ route('skills.destroy', $skill) }}" method="POST" onsubmit="return confirm('Delete this skill?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                @empty
                                    <span class="text-xs text-gray-400 italic">No skills in this category</span>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
