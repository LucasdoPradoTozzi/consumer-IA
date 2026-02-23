<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Manage Languages & Proficiency Levels') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            
            <!-- Proficiency Levels Management -->
            <div class="bg-white p-8 md:p-10 rounded-3xl shadow-sm border border-gray-100">
                <h3 class="text-lg font-bold text-indigo-700 mb-6 border-b pb-2">Proficiency Levels</h3>
                
                <form action="{{ route('languages.levels.store') }}" method="POST" class="mb-8 flex gap-4">
                    @csrf
                    <div class="flex-1">
                        <x-text-input name="name" type="text" placeholder="New Level Name (e.g. Native)" class="w-full" required />
                    </div>
                    <x-primary-button>Add Level</x-primary-button>
                </form>

                <div class="flex flex-wrap gap-3">
                    @foreach($levels as $level)
                        <div class="flex items-center gap-2 bg-indigo-50 text-indigo-700 px-4 py-2 rounded-full border border-indigo-100">
                            <span class="font-bold text-sm">{{ $level->name }}</span>
                            <form action="{{ route('languages.levels.destroy', $level) }}" method="POST" onsubmit="return confirm('Delete this level? Only possible if not used.')">
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

            <!-- Languages Management -->
            <div class="bg-white p-8 md:p-10 rounded-3xl shadow-sm border border-gray-100">
                <h3 class="text-lg font-bold text-indigo-700 mb-6 border-b pb-2">Languages</h3>

                <form action="{{ route('languages.store') }}" method="POST" class="mb-10 flex gap-4 bg-gray-50 p-6 rounded-2xl border border-gray-100">
                    @csrf
                    <div class="flex-1">
                        <x-text-input name="name" type="text" placeholder="Add Language (e.g. French)" class="w-full" required />
                    </div>
                    <x-primary-button>Add Language</x-primary-button>
                </form>

                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    @foreach($languages as $language)
                        <div class="p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md transition flex justify-between items-center group">
                            <span class="text-sm font-bold text-gray-700">{{ $language->name }}</span>
                            <form action="{{ route('languages.destroy', $language) }}" method="POST" onsubmit="return confirm('Delete this language?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
