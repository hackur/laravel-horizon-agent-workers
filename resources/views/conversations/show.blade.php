<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <a href="{{ route('conversations.index') }}" class="text-blue-600 hover:text-blue-900 text-sm">&larr; Back to Conversations</a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight mt-1">
                    {{ $conversation->title }}
                </h2>
            </div>
            <div class="flex items-center space-x-2">
                <span class="text-xs px-3 py-1 bg-gray-100 text-gray-700 rounded-full">
                    {{ $providers[$conversation->provider]['name'] ?? $conversation->provider }}
                </span>
                @if($conversation->model)
                    <span class="text-xs px-3 py-1 bg-blue-100 text-blue-700 rounded-full">
                        {{ $conversation->model }}
                    </span>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Live Status Indicator -->
            <div id="live-indicator" class="bg-green-50 border border-green-200 rounded-lg px-4 py-2 mb-4 hidden">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="animate-pulse h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <circle cx="10" cy="10" r="8"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800">
                            Connected - Real-time updates active
                        </p>
                    </div>
                </div>
            </div>

            <!-- Conversation Messages -->
            <div id="messages-container" class="space-y-4 mb-6">
                @forelse($conversation->messages as $message)
                    <div class="flex {{ $message->role === 'user' ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-3xl w-full {{ $message->role === 'user' ? 'ml-12' : 'mr-12' }}">
                            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                                <!-- Role Badge -->
                                <div class="flex items-start justify-between mb-3">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $message->role === 'user' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                        {{ ucfirst($message->role) }}
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        {{ $message->created_at->format('M d, Y H:i') }}
                                    </span>
                                </div>

                                <!-- Message Content -->
                                <div class="prose max-w-none">
                                    <pre class="whitespace-pre-wrap text-sm text-gray-800 font-sans">{{ $message->content }}</pre>
                                </div>

                                <!-- LLM Query Metadata (for assistant messages) -->
                                @if($message->role === 'assistant' && $message->llmQuery)
                                    <div class="mt-4 pt-4 border-t border-gray-200">
                                        <div class="flex items-center justify-between text-xs">
                                            <!-- Usage Stats -->
                                            @if($message->llmQuery->usage_stats)
                                                <div class="flex space-x-3">
                                                    @if(isset($message->llmQuery->usage_stats['total_tokens']))
                                                        <span class="inline-flex items-center px-2 py-1 bg-purple-50 text-purple-700 rounded">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path>
                                                            </svg>
                                                            {{ number_format($message->llmQuery->usage_stats['total_tokens']) }} tokens
                                                        </span>
                                                    @endif
                                                    @if($message->llmQuery->duration_ms)
                                                        <span class="inline-flex items-center px-2 py-1 bg-blue-50 text-blue-700 rounded">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                            </svg>
                                                            {{ round($message->llmQuery->duration_ms / 1000, 2) }}s
                                                        </span>
                                                    @endif
                                                    @if($message->llmQuery->finish_reason)
                                                        <span class="inline-flex items-center px-2 py-1 bg-gray-50 text-gray-700 rounded">
                                                            {{ $message->llmQuery->finish_reason }}
                                                        </span>
                                                    @endif
                                                </div>
                                            @endif

                                            <!-- View Query Details Link -->
                                            <a href="{{ route('llm-queries.show', $message->llmQuery) }}"
                                               class="text-blue-600 hover:text-blue-900">
                                                View Details →
                                            </a>
                                        </div>

                                        <!-- Reasoning Content (Collapsible) -->
                                        @if($message->llmQuery->reasoning_content)
                                            <details class="mt-3">
                                                <summary class="cursor-pointer text-sm text-gray-600 hover:text-gray-900 flex items-center">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                    </svg>
                                                    Show Reasoning
                                                </summary>
                                                <div class="mt-2 p-3 bg-gray-50 rounded text-xs">
                                                    <pre class="whitespace-pre-wrap text-gray-700">{{ $message->llmQuery->reasoning_content }}</pre>
                                                </div>
                                            </details>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="bg-white shadow-sm sm:rounded-lg p-12 text-center">
                        <p class="text-gray-500">No messages yet in this conversation.</p>
                    </div>
                @endforelse
            </div>

            <!-- Check for pending/processing queries -->
            @php
                $hasPendingQuery = $conversation->queries()
                    ->whereIn('status', ['pending', 'processing'])
                    ->exists();
            @endphp

            @if($hasPendingQuery)
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-center">
                    <div class="flex items-center justify-center space-x-2">
                        <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-blue-800">Processing your message...</span>
                    </div>
                    <p class="text-xs text-blue-600 mt-2">Response will appear automatically when ready</p>
                </div>
            @endif

            <!-- Add New Message Form -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Continue Conversation</h3>
                <form method="POST" action="{{ route('conversations.add-message', $conversation) }}">
                    @csrf
                    <div class="mb-4">
                        <textarea name="prompt" rows="4" required
                                  class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                  placeholder="Type your message...">{{ old('prompt') }}</textarea>
                        @error('prompt')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex justify-end">
                        <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded"
                                {{ $hasPendingQuery ? 'disabled' : '' }}>
                            {{ $hasPendingQuery ? 'Processing...' : 'Send Message' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @vite(['resources/js/app.js'])

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const conversationId = {{ $conversation->id }};
            const messagesContainer = document.getElementById('messages-container');
            const liveIndicator = document.getElementById('live-indicator');
            const submitButton = document.querySelector('button[type="submit"]');

            // Show live indicator when Echo is ready
            if (window.Echo) {
                liveIndicator.classList.remove('hidden');

                // Listen for new messages in this conversation
                window.Echo.private(`conversations.${conversationId}`)
                    .listen('.message.received', (event) => {
                        console.log('New message received:', event);

                        // Add the new assistant message to the UI
                        const messageHtml = createMessageElement(event.message, event.query_status);
                        messagesContainer.insertAdjacentHTML('beforeend', messageHtml);

                        // Scroll to the new message
                        messagesContainer.lastElementChild.scrollIntoView({ behavior: 'smooth' });

                        // Re-enable submit button if it was disabled
                        if (submitButton) {
                            submitButton.disabled = false;
                            submitButton.textContent = 'Send Message';
                        }

                        // Show notification
                        showNotification('New response received!', 'success');
                    });

                console.log(`Subscribed to conversation ${conversationId}`);
            }

            function createMessageElement(message, queryStatus) {
                const timestamp = new Date(message.created_at).toLocaleString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });

                return `
                    <div class="flex justify-start animate-fade-in">
                        <div class="max-w-3xl w-full mr-12">
                            <div class="bg-white shadow-sm sm:rounded-lg p-6 border-2 border-green-200">
                                <div class="flex items-start justify-between mb-3">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Assistant <span class="ml-1 text-xs">●</span>
                                    </span>
                                    <span class="text-xs text-gray-500">${timestamp}</span>
                                </div>
                                <div class="prose max-w-none">
                                    <pre class="whitespace-pre-wrap text-sm text-gray-800 font-sans">${escapeHtml(message.content)}</pre>
                                </div>
                                ${queryStatus && queryStatus.duration_ms ? `
                                    <div class="mt-3 pt-3 border-t border-gray-200 text-xs text-gray-500">
                                        Response time: ${(queryStatus.duration_ms / 1000).toFixed(2)}s
                                        ${queryStatus.reasoning_content ? ' • Includes reasoning' : ''}
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
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

            // Add CSS for animations
            const style = document.createElement('style');
            style.textContent = `
                @keyframes fade-in {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                @keyframes slide-in {
                    from { transform: translateX(100%); }
                    to { transform: translateX(0); }
                }
                .animate-fade-in {
                    animation: fade-in 0.5s ease-out;
                }
                .animate-slide-in {
                    animation: slide-in 0.3s ease-out;
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</x-app-layout>
