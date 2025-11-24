<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Create New LLM Query</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <a href="{{ route('llm-queries.index') }}" class="text-blue-600 hover:text-blue-900">&larr; Back to Queries</a>
                    </div>

                    <form method="POST" action="{{ route('llm-queries.store') }}" class="space-y-6">
                        @csrf

                        <div>
                            <label for="provider" class="block text-sm font-medium text-gray-700">Provider *</label>
                            <select id="provider" name="provider" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" onchange="updateModels()">
                                <option value="">Select a provider...</option>
                                @foreach($providers as $key => $provider)
                                    <option value="{{ $key }}">{{ $provider['name'] }} - {{ $provider['description'] }}</option>
                                @endforeach
                            </select>
                            @error('provider')
                                <p class="mt-1 text-red-600 text-sm">{{ $message }}</p>
                            @enderror
                        </div>

                        <div id="modelSection" class="hidden">
                            <label for="model" class="block text-sm font-medium text-gray-700">Model</label>
                            <select id="model" name="model" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <option value="">Default model</option>
                            </select>
                            @error('model')
                                <p class="mt-1 text-red-600 text-sm">{{ $message }}</p>
                            @enderror
                        </div>

                        <div id="commandSection" class="hidden">
                            <label for="command" class="block text-sm font-medium text-gray-700">Command</label>
                            <input id="command" name="options[command]" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" placeholder="e.g. claude {prompt} or ollama run {model} {prompt}" />
                            <p class="mt-1 text-xs text-gray-500">Shown for Local Command provider. Use {prompt} and optionally {model} placeholders.</p>
                        </div>

                        <div>
                            <label for="prompt" class="block text-sm font-medium text-gray-700">Prompt *</label>
                            <textarea id="prompt" name="prompt" rows="6" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" placeholder="Enter your prompt here...">{{ old('prompt') }}</textarea>
                            @error('prompt')
                                <p class="mt-1 text-red-600 text-sm">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-between">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">Dispatch Query</button>
                            <a href="{{ route('llm-queries.index') }}" class="text-gray-600 hover:text-gray-900">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const providers = @json($providers);
        function updateModels() {
            const providerSelect = document.getElementById('provider');
            const modelSelect = document.getElementById('model');
            const modelSection = document.getElementById('modelSection');
            const commandSection = document.getElementById('commandSection');
            const selectedProvider = providerSelect.value;
            if (selectedProvider && providers[selectedProvider]) {
                const models = providers[selectedProvider].models;
                modelSelect.innerHTML = '<option value="">Default model</option>';
                models.forEach(model => {
                    const option = document.createElement('option');
                    option.value = model;
                    option.textContent = model;
                    modelSelect.appendChild(option);
                });
                modelSection.classList.toggle('hidden', models.length === 0);
                commandSection.classList.toggle('hidden', selectedProvider !== 'local-command');
            } else {
                modelSection.classList.add('hidden');
                commandSection.classList.add('hidden');
            }
        }
    </script>
</x-app-layout>
