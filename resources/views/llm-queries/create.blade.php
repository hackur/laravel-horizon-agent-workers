<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create LLM Query</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8 max-w-2xl">
        <div class="mb-6">
            <a href="{{ route('llm-queries.index') }}" class="text-blue-600 hover:text-blue-900">&larr; Back to Queries</a>
        </div>

        <div class="bg-white shadow-md rounded-lg p-6">
            <h1 class="text-2xl font-bold mb-6">Create New LLM Query</h1>

            <form method="POST" action="{{ route('llm-queries.store') }}">
                @csrf

                <div class="mb-4">
                    <label for="provider" class="block text-gray-700 text-sm font-bold mb-2">Provider *</label>
                    <select id="provider" name="provider" required class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" onchange="updateModels()">
                        <option value="">Select a provider...</option>
                        @foreach($providers as $key => $provider)
                            <option value="{{ $key }}">{{ $provider['name'] }} - {{ $provider['description'] }}</option>
                        @endforeach
                    </select>
                    @error('provider')
                        <p class="text-red-500 text-xs italic">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4" id="modelSection" style="display: none;">
                    <label for="model" class="block text-gray-700 text-sm font-bold mb-2">Model</label>
                    <select id="model" name="model" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">Default model</option>
                    </select>
                    @error('model')
                        <p class="text-red-500 text-xs italic">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="prompt" class="block text-gray-700 text-sm font-bold mb-2">Prompt *</label>
                    <textarea id="prompt" name="prompt" rows="6" required class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Enter your prompt here...">{{ old('prompt') }}</textarea>
                    @error('prompt')
                        <p class="text-red-500 text-xs italic">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Dispatch Query
                    </button>
                    <a href="{{ route('llm-queries.index') }}" class="text-gray-600 hover:text-gray-900">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        const providers = @json($providers);

        function updateModels() {
            const providerSelect = document.getElementById('provider');
            const modelSelect = document.getElementById('model');
            const modelSection = document.getElementById('modelSection');
            const selectedProvider = providerSelect.value;

            if (selectedProvider && providers[selectedProvider]) {
                const models = providers[selectedProvider].models;

                // Clear existing options
                modelSelect.innerHTML = '<option value="">Default model</option>';

                // Add new options
                models.forEach(model => {
                    const option = document.createElement('option');
                    option.value = model;
                    option.textContent = model;
                    modelSelect.appendChild(option);
                });

                modelSection.style.display = models.length > 0 ? 'block' : 'none';
            } else {
                modelSection.style.display = 'none';
            }
        }
    </script>
</body>
</html>
