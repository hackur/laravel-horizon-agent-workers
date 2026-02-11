<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">🚀 New Agent Run</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form action="{{ route('agent-runs.store') }}" method="POST">
                    @csrf

                    @if($errors->any())
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            <ul class="list-disc list-inside">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Mode Selection -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Run Mode</label>
                        <div class="flex space-x-4">
                            <label class="flex items-center">
                                <input type="radio" name="mode" value="orchestrator" checked class="mr-2">
                                <span class="text-sm">🎭 Orchestrator (Agent + Reviewer loop)</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="mode" value="simple" class="mr-2">
                                <span class="text-sm">⚡ Simple (Single agent run)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Task -->
                    <div class="mb-6">
                        <label for="task" class="block text-sm font-medium text-gray-700 mb-2">Task Description</label>
                        <textarea
                            name="task"
                            id="task"
                            rows="6"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Describe the task for the agent...

Example:
Refactor the UserController to use DTOs for data transfer. Create a UserDTO class and update all methods to use it instead of raw arrays."
                            required
                        >{{ old('task') }}</textarea>
                        <p class="mt-1 text-sm text-gray-500">Be specific about what you want the agent to accomplish.</p>
                    </div>

                    <!-- Working Directory -->
                    <div class="mb-6">
                        <label for="working_directory" class="block text-sm font-medium text-gray-700 mb-2">Working Directory</label>
                        <input
                            type="text"
                            name="working_directory"
                            id="working_directory"
                            value="{{ old('working_directory', base_path()) }}"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm"
                            required
                        >
                        <p class="mt-1 text-sm text-gray-500">The project directory where the agent will work.</p>
                    </div>

                    <!-- Advanced Options (collapsible) -->
                    <details class="mb-6">
                        <summary class="cursor-pointer text-sm font-medium text-gray-700 mb-2">Advanced Options</summary>
                        <div class="mt-4 space-y-4 bg-gray-50 p-4 rounded-md">
                            <!-- Session Key -->
                            <div>
                                <label for="session" class="block text-sm font-medium text-gray-700 mb-1">Session Key (optional)</label>
                                <input
                                    type="text"
                                    name="session"
                                    id="session"
                                    value="{{ old('session') }}"
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="my-project-refactor"
                                >
                                <p class="mt-1 text-xs text-gray-500">Session key for context persistence across iterations.</p>
                            </div>

                            <!-- Max Iterations -->
                            <div class="orchestrator-only">
                                <label for="max_iterations" class="block text-sm font-medium text-gray-700 mb-1">Max Iterations</label>
                                <input
                                    type="number"
                                    name="max_iterations"
                                    id="max_iterations"
                                    value="{{ old('max_iterations', 5) }}"
                                    min="1"
                                    max="20"
                                    class="w-32 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                <p class="mt-1 text-xs text-gray-500">Maximum agent-reviewer cycles before stopping.</p>
                            </div>

                            <!-- Models -->
                            <div class="grid grid-cols-2 gap-4 orchestrator-only">
                                <div>
                                    <label for="agent_model" class="block text-sm font-medium text-gray-700 mb-1">Agent Model</label>
                                    <select name="agent_model" id="agent_model" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Default (from config)</option>
                                        <option value="sonnet">Sonnet (fast)</option>
                                        <option value="opus">Opus (thorough)</option>
                                        <option value="haiku">Haiku (quick)</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="reviewer_model" class="block text-sm font-medium text-gray-700 mb-1">Reviewer Model</label>
                                    <select name="reviewer_model" id="reviewer_model" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Default (from config)</option>
                                        <option value="opus">Opus (recommended)</option>
                                        <option value="sonnet">Sonnet</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </details>

                    <!-- Submit -->
                    <div class="flex justify-between items-center">
                        <a href="{{ route('agent-runs.index') }}" class="text-gray-600 hover:text-gray-900">← Back to runs</a>
                        <button type="submit" class="inline-flex items-center px-6 py-3 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            🚀 Dispatch Agent Run
                        </button>
                    </div>
                </form>
            </div>

            <!-- Info Box -->
            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h3 class="font-semibold text-blue-800 mb-2">How it works</h3>
                <ol class="list-decimal list-inside text-sm text-blue-700 space-y-1">
                    <li><strong>Agent</strong> executes your task and produces output</li>
                    <li><strong>Reviewer</strong> evaluates the output and provides feedback</li>
                    <li>If not approved, agent <strong>iterates</strong> with reviewer feedback</li>
                    <li>Continues until approved or max iterations reached</li>
                </ol>
            </div>
        </div>
    </div>

    <script>
        // Toggle orchestrator-only fields based on mode
        document.querySelectorAll('input[name="mode"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const orchestratorOnly = document.querySelectorAll('.orchestrator-only');
                orchestratorOnly.forEach(el => {
                    el.style.display = this.value === 'orchestrator' ? 'block' : 'none';
                });
            });
        });
    </script>
</x-app-layout>
