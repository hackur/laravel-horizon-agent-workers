<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Start New Conversation') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('conversations.store') }}">
                    @csrf

                    <!-- Title -->
                    <div class="mb-6">
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                            Conversation Title *
                        </label>
                        <input type="text" id="title" name="title" required
                               value="{{ old('title') }}"
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                               placeholder="e.g., Help with Laravel Queries">
                        @error('title')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Provider Selection -->
                    <div class="mb-6">
                        <label for="provider" class="block text-sm font-medium text-gray-700 mb-2">
                            AI Provider *
                        </label>
                        <div class="space-y-3">
                            @foreach($providers as $key => $provider)
                                <label class="relative flex items-start p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition">
                                    <input type="radio" name="provider" value="{{ $key }}" required
                                           class="mt-1 mr-3" onchange="updateModels('{{ $key }}')">
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-gray-900">{{ $provider['name'] }}</span>
                                            <span class="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded">
                                                {{ $provider['queue'] }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-500 mt-1">{{ $provider['description'] }}</p>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        @error('provider')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Model Selection (Dynamic) -->
                    <div id="modelSection" class="mb-6 hidden">
                        <label for="model" class="block text-sm font-medium text-gray-700 mb-2">
                            Model (Optional)
                        </label>
                        <select id="model" name="model"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                            <option value="">Default model</option>
                        </select>
                        @error('model')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Initial Prompt -->
                    <div class="mb-6">
                        <label for="prompt" class="block text-sm font-medium text-gray-700 mb-2">
                            Your First Message *
                        </label>
                        <textarea id="prompt" name="prompt" rows="6" required
                                  class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                  placeholder="Start your conversation...">{{ old('prompt') }}</textarea>
                        @error('prompt')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-between">
                        <a href="{{ route('conversations.index') }}"
                           class="text-gray-600 hover:text-gray-900">
                            Cancel
                        </a>
                        <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded">
                            Start Conversation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const providers = @json($providers);

        function updateModels(providerKey) {
            const modelSelect = document.getElementById('model');
            const modelSection = document.getElementById('modelSection');
            const provider = providers[providerKey];

            if (provider && provider.models && provider.models.length > 0) {
                modelSelect.innerHTML = '<option value="">Default model</option>';
                provider.models.forEach(model => {
                    const option = document.createElement('option');
                    option.value = model;
                    option.textContent = model;
                    modelSelect.appendChild(option);
                });
                modelSection.classList.remove('hidden');
            } else {
                modelSection.classList.add('hidden');
            }
        }
    </script>
</x-app-layout>
