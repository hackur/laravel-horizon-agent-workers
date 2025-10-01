<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Query #{{ $query->id }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <div class="mb-6">
            <a href="{{ route('llm-queries.index') }}" class="text-blue-600 hover:text-blue-900">&larr; Back to Queries</a>
        </div>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white shadow-md rounded-lg p-6 mb-6">
            <div class="flex justify-between items-start mb-4">
                <h1 class="text-2xl font-bold">Query #{{ $query->id }}</h1>
                <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full
                    {{ $query->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                    {{ $query->status === 'processing' ? 'bg-yellow-100 text-yellow-800' : '' }}
                    {{ $query->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}
                    {{ $query->status === 'pending' ? 'bg-gray-100 text-gray-800' : '' }}">
                    {{ ucfirst($query->status) }}
                </span>
            </div>

            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="font-medium text-gray-500">Provider</dt>
                    <dd class="mt-1 text-gray-900">{{ $query->provider }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Model</dt>
                    <dd class="mt-1 text-gray-900">{{ $query->model ?? 'Default' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Duration</dt>
                    <dd class="mt-1 text-gray-900">{{ $query->duration_ms ? round($query->duration_ms / 1000, 2) . ' seconds' : 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Created</dt>
                    <dd class="mt-1 text-gray-900">{{ $query->created_at->format('Y-m-d H:i:s') }}</dd>
                </div>
                @if($query->completed_at)
                <div>
                    <dt class="font-medium text-gray-500">Completed</dt>
                    <dd class="mt-1 text-gray-900">{{ $query->completed_at->format('Y-m-d H:i:s') }}</dd>
                </div>
                @endif
                @if($query->finish_reason)
                <div>
                    <dt class="font-medium text-gray-500">Finish Reason</dt>
                    <dd class="mt-1 text-gray-900">{{ $query->finish_reason }}</dd>
                </div>
                @endif
            </dl>

            <!-- Usage Statistics -->
            @if($query->usage_stats)
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h3 class="text-lg font-semibold mb-3">Usage Statistics</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        @if(isset($query->usage_stats['input_tokens']))
                            <div class="bg-blue-50 rounded-lg p-3">
                                <div class="text-xs text-blue-600 font-medium mb-1">Input Tokens</div>
                                <div class="text-xl font-bold text-blue-900">{{ number_format($query->usage_stats['input_tokens']) }}</div>
                            </div>
                        @endif
                        @if(isset($query->usage_stats['output_tokens']))
                            <div class="bg-green-50 rounded-lg p-3">
                                <div class="text-xs text-green-600 font-medium mb-1">Output Tokens</div>
                                <div class="text-xl font-bold text-green-900">{{ number_format($query->usage_stats['output_tokens']) }}</div>
                            </div>
                        @endif
                        @if(isset($query->usage_stats['total_tokens']))
                            <div class="bg-purple-50 rounded-lg p-3">
                                <div class="text-xs text-purple-600 font-medium mb-1">Total Tokens</div>
                                <div class="text-xl font-bold text-purple-900">{{ number_format($query->usage_stats['total_tokens']) }}</div>
                            </div>
                        @endif
                        @if(isset($query->usage_stats['cache_read_tokens']))
                            <div class="bg-yellow-50 rounded-lg p-3">
                                <div class="text-xs text-yellow-600 font-medium mb-1">Cache Read</div>
                                <div class="text-xl font-bold text-yellow-900">{{ number_format($query->usage_stats['cache_read_tokens']) }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <div class="bg-white shadow-md rounded-lg p-6 mb-6">
            <h2 class="text-xl font-bold mb-4">Prompt</h2>
            <div class="bg-gray-50 p-4 rounded border border-gray-200">
                <pre class="whitespace-pre-wrap text-sm">{{ $query->prompt }}</pre>
            </div>
        </div>

        @if($query->response)
        <div class="bg-white shadow-md rounded-lg p-6 mb-6">
            <h2 class="text-xl font-bold mb-4">Response</h2>
            <div class="bg-gray-50 p-4 rounded border border-gray-200">
                <pre class="whitespace-pre-wrap text-sm">{{ $query->response }}</pre>
            </div>
        </div>
        @endif

        @if($query->reasoning_content)
        <div class="bg-white shadow-md rounded-lg p-6 mb-6">
            <details class="group">
                <summary class="cursor-pointer flex items-center justify-between text-xl font-bold mb-4 hover:text-blue-600">
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-2 transform group-open:rotate-90 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                        Reasoning Content
                    </span>
                    <span class="text-xs px-3 py-1 bg-blue-100 text-blue-700 rounded-full font-normal">
                        Click to expand
                    </span>
                </summary>
                <div class="mt-4 bg-blue-50 p-4 rounded border border-blue-200">
                    <pre class="whitespace-pre-wrap text-sm text-gray-800">{{ $query->reasoning_content }}</pre>
                </div>
            </details>
        </div>
        @endif

        @if($query->error)
        <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-6">
            <h2 class="text-xl font-bold text-red-800 mb-4">Error</h2>
            <div class="bg-white p-4 rounded border border-red-300">
                <pre class="whitespace-pre-wrap text-sm text-red-700">{{ $query->error }}</pre>
            </div>
        </div>
        @endif

        <!-- Live Status Indicator -->
        <div id="live-status" class="hidden fixed bottom-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg">
            <div class="flex items-center space-x-2">
                <svg class="animate-pulse h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                    <circle cx="10" cy="10" r="8"/>
                </svg>
                <span class="text-sm font-medium">Live Updates Active</span>
            </div>
        </div>
    </div>

    @vite(['resources/js/app.js'])

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const queryId = {{ $query->id }};
            const currentStatus = '{{ $query->status }}';

            if (window.Echo && ['pending', 'processing'].includes(currentStatus)) {
                const liveStatus = document.getElementById('live-status');
                if (liveStatus) {
                    liveStatus.classList.remove('hidden');
                }

                // Listen for query status updates
                window.Echo.private(`queries.${queryId}`)
                    .listen('.status.updated', (event) => {
                        console.log('Query status updated:', event);

                        // Reload the page when query completes or fails
                        if (event.status === 'completed' || event.status === 'failed') {
                            showNotification(
                                event.status === 'completed' ? 'Query completed!' : 'Query failed',
                                event.status === 'completed' ? 'success' : 'error'
                            );

                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else if (event.status === 'processing') {
                            showNotification('Query is now processing...', 'info');
                        }
                    });

                console.log(`Subscribed to query ${queryId} updates`);
            }

            function showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg ${
                    type === 'success' ? 'bg-green-500' :
                    type === 'error' ? 'bg-red-500' : 'bg-blue-500'
                } text-white z-50 animate-slide-in`;
                notification.textContent = message;
                document.body.appendChild(notification);

                setTimeout(() => {
                    notification.remove();
                }, 3000);
            }

            // Add CSS for animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slide-in {
                    from { transform: translateX(100%); }
                    to { transform: translateX(0); }
                }
                .animate-slide-in {
                    animation: slide-in 0.3s ease-out;
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>
