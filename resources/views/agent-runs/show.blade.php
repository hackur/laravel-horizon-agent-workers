<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            🎭 Agent Run #{{ $run->id }}
            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                {{ $run->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                {{ $run->status === 'running' ? 'bg-blue-100 text-blue-800' : '' }}
                {{ $run->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}
                {{ $run->status === 'max_iterations_reached' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                {{ $run->status }}
            </span>
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Back Button -->
            <div class="mb-4">
                <a href="{{ route('agent-runs.index') }}" class="text-gray-600 hover:text-gray-900">← Back to runs</a>
            </div>

            <!-- Run Details -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">Run Details</h3>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div>
                        <div class="text-sm text-gray-500">Status</div>
                        <div class="font-semibold">{{ $run->summary }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Iterations</div>
                        <div class="font-semibold">{{ $run->iterations_used ?? 0 }} / {{ $run->max_iterations }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Duration</div>
                        <div class="font-semibold">
                            @if($run->duration)
                                {{ gmdate('H:i:s', $run->duration) }}
                            @elseif($run->isRunning() && $run->started_at)
                                <span class="text-blue-600">{{ gmdate('H:i:s', now()->diffInSeconds($run->started_at)) }} (running)</span>
                            @else
                                -
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Models</div>
                        <div class="font-semibold">
                            <span class="text-gray-700">{{ $run->agent_model }}</span>
                            <span class="text-gray-400">/</span>
                            <span class="text-purple-600">{{ $run->reviewer_model }}</span>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="text-sm text-gray-500 mb-1">Task</div>
                    <div class="bg-gray-50 p-4 rounded-md font-mono text-sm whitespace-pre-wrap">{{ $run->task }}</div>
                </div>

                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">Working Directory:</span>
                        <code class="ml-2 bg-gray-100 px-2 py-1 rounded">{{ $run->working_directory }}</code>
                    </div>
                    <div>
                        <span class="text-gray-500">Session:</span>
                        <code class="ml-2 bg-gray-100 px-2 py-1 rounded">{{ $run->session_key ?? 'N/A' }}</code>
                    </div>
                    <div>
                        <span class="text-gray-500">Started:</span>
                        <span class="ml-2">{{ $run->started_at?->format('Y-m-d H:i:s') ?? 'Not started' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Completed:</span>
                        <span class="ml-2">{{ $run->completed_at?->format('Y-m-d H:i:s') ?? 'In progress' }}</span>
                    </div>
                </div>

                @if($run->error_message)
                    <div class="mt-4 bg-red-50 border border-red-200 rounded-md p-4">
                        <div class="text-sm font-semibold text-red-800 mb-1">Error</div>
                        <div class="text-sm text-red-700 font-mono">{{ $run->error_message }}</div>
                    </div>
                @endif
            </div>

            <!-- Iteration Timeline -->
            @if($run->reviews->count() > 0 || $run->outputs->count() > 0)
                <div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-semibold mb-4">Iteration Timeline</h3>
                    
                    <div class="space-y-6">
                        @php
                            $maxIteration = max($run->outputs->max('iteration') ?? 0, $run->reviews->max('iteration') ?? 0);
                        @endphp
                        
                        @for($i = 1; $i <= $maxIteration; $i++)
                            @php
                                $output = $run->outputs->where('iteration', $i)->where('type', 'agent')->first();
                                $review = $run->reviews->where('iteration', $i)->first();
                            @endphp
                            
                            <div class="border-l-4 {{ $review && $review->approved ? 'border-green-500' : 'border-blue-500' }} pl-4">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-semibold text-gray-800">Iteration {{ $i }}</h4>
                                    @if($review)
                                        <span class="px-2 py-1 text-xs rounded-full {{ $review->approved ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                            {{ $review->approved ? '✅ Approved' : '🔄 Changes Requested' }}
                                            @if($review->score)
                                                ({{ $review->score }}/10)
                                            @endif
                                        </span>
                                    @endif
                                </div>
                                
                                @if($output)
                                    <details class="mb-3">
                                        <summary class="cursor-pointer text-sm text-gray-600 hover:text-gray-900">
                                            🤖 Agent Output ({{ strlen($output->content) }} chars)
                                        </summary>
                                        <div class="mt-2 bg-gray-50 p-4 rounded-md font-mono text-xs whitespace-pre-wrap max-h-96 overflow-y-auto">{{ $output->content }}</div>
                                    </details>
                                @endif
                                
                                @if($review && $review->feedback)
                                    <details class="mb-3" {{ !$review->approved ? 'open' : '' }}>
                                        <summary class="cursor-pointer text-sm text-gray-600 hover:text-gray-900">
                                            📋 Reviewer Feedback
                                        </summary>
                                        <div class="mt-2 bg-purple-50 p-4 rounded-md text-sm whitespace-pre-wrap">{{ $review->feedback }}</div>
                                    </details>
                                @endif
                            </div>
                        @endfor
                    </div>
                </div>
            @endif

            <!-- Final Output -->
            @if($run->final_output)
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">✅ Final Output</h3>
                    <div class="bg-green-50 p-4 rounded-md font-mono text-sm whitespace-pre-wrap max-h-[600px] overflow-y-auto">{{ $run->final_output }}</div>
                </div>
            @endif

            <!-- Auto-refresh for running jobs -->
            @if($run->isRunning())
                <script>
                    setTimeout(() => location.reload(), 5000);
                </script>
                <div class="mt-4 text-center text-sm text-gray-500">
                    🔄 Auto-refreshing every 5 seconds...
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
