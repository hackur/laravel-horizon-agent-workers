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
                        <button id="submitButton" type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center">
                            <svg id="submitSpinner" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span id="submitText">Start Conversation</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const providers = @json($providers);

        // Form submission loading state
        const form = document.querySelector('form');
        const submitButton = document.getElementById('submitButton');
        const submitSpinner = document.getElementById('submitSpinner');
        const submitText = document.getElementById('submitText');

        form.addEventListener('submit', function() {
            submitButton.disabled = true;
            submitSpinner.classList.remove('hidden');
            submitText.textContent = 'Creating...';
        });

        async function updateModels(providerKey) {
            const modelSelect = document.getElementById('model');
            const modelSection = document.getElementById('modelSection');
            const provider = providers[providerKey];

            // For LM Studio, fetch models from API
            if (providerKey === 'lmstudio') {
                modelSelect.innerHTML = '<option value="">Loading models...</option>';
                modelSection.classList.remove('hidden');

                try {
                    const response = await fetch('/api/lmstudio/models');
                    const data = await response.json();

                    if (data.success && data.models.length > 0) {
                        modelSelect.innerHTML = '<option value="">Select a model</option>';
                        data.models.forEach(model => {
                            const option = document.createElement('option');
                            option.value = model;
                            option.textContent = model;
                            modelSelect.appendChild(option);
                        });
                    } else {
                        modelSelect.innerHTML = '<option value="">No models available (is LM Studio running?)</option>';
                    }
                } catch (error) {
                    console.error('Failed to fetch LM Studio models:', error);
                    modelSelect.innerHTML = '<option value="">Failed to load models</option>';
                }
            }
            // For other providers with static models
            else if (provider && provider.models && provider.models.length > 0) {
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
