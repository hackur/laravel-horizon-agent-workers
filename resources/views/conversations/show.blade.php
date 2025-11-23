<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex-1">
                <a href="{{ route('conversations.index') }}" class="text-blue-600 hover:text-blue-900 text-sm">&larr; Back to Conversations</a>
                <div id="titleContainer" class="mt-1">
                    <h2 id="titleDisplay" class="font-semibold text-xl text-gray-800 leading-tight cursor-pointer hover:text-blue-600 inline-flex items-center group">
                        <span id="titleText">{{ $conversation->title }}</span>
                        <svg class="w-4 h-4 ml-2 opacity-0 group-hover:opacity-50 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                        </svg>
                    </h2>
                    <form id="titleForm" method="POST" action="{{ route('conversations.update', $conversation) }}" class="hidden">
                        @csrf
                        @method('PUT')
                        <input type="text" id="titleInput" name="title" value="{{ $conversation->title }}"
                               class="font-semibold text-xl text-gray-800 border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                               style="width: 400px; max-width: 100%;">
                        <button type="submit" class="ml-2 text-xs px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
                        <button type="button" id="cancelEdit" class="text-xs px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">Cancel</button>
                    </form>
                </div>
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
                <form method="POST" action="{{ route('conversations.destroy', $conversation) }}"
                      onsubmit="return confirm('Are you sure you want to delete this conversation? This action cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="text-xs px-3 py-1 bg-red-100 text-red-700 rounded-full hover:bg-red-200 transition">
                        Delete
                    </button>
                </form>
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
                            <div class="bg-white shadow-sm sm:rounded-lg p-6 {{ $message->role === 'assistant' ? 'bg-gray-50' : '' }}">
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
                                <div class="markdown-content" data-raw-content="{{ base64_encode($message->content) }}">
                                    <!-- Markdown will be rendered here by JavaScript -->
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
                                                <div class="mt-2 p-3 bg-white rounded text-xs border border-gray-200">
                                                    <div class="markdown-content" data-raw-content="{{ base64_encode($message->llmQuery->reasoning_content) }}"></div>
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
                <form id="messageForm" method="POST" action="{{ route('conversations.add-message', $conversation) }}">
                    @csrf
                    <div class="mb-4">
                        <textarea id="messageInput" name="prompt" rows="4" required
                                  class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                  placeholder="Type your message..."
                                  {{ $hasPendingQuery ? 'disabled' : '' }}>{{ old('prompt') }}</textarea>
                        @error('prompt')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex justify-end">
                        <button id="sendButton" type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center"
                                {{ $hasPendingQuery ? 'disabled' : '' }}>
                            <svg id="loadingSpinner" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span id="buttonText">{{ $hasPendingQuery ? 'Processing...' : 'Send Message' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @vite(['resources/js/app.js'])

    <!-- Highlight.js for syntax highlighting -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.10.0/styles/github-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.10.0/highlight.min.js"></script>

    <script type="module">
        import { marked } from 'https://cdn.jsdelivr.net/npm/marked@16.3.0/+esm';
        import DOMPurify from 'https://cdn.jsdelivr.net/npm/dompurify@3.2.7/+esm';

        // Configure marked for better rendering
        marked.setOptions({
            breaks: true,
            gfm: true,
            headerIds: false,
            mangle: false
        });

        // Function to render markdown safely
        function renderMarkdown(content) {
            const rawHtml = marked.parse(content);
            return DOMPurify.sanitize(rawHtml, {
                ALLOWED_TAGS: ['p', 'br', 'strong', 'em', 'u', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'code', 'pre', 'a', 'table', 'thead', 'tbody', 'tr', 'th', 'td'],
                ALLOWED_ATTR: ['href', 'class']
            });
        }

        // Add copy buttons and syntax highlighting to code blocks
        function addCopyButtons(container) {
            container.querySelectorAll('pre code').forEach(codeBlock => {
                const pre = codeBlock.parentElement;

                // Apply syntax highlighting
                if (window.hljs && !codeBlock.classList.contains('hljs')) {
                    hljs.highlightElement(codeBlock);
                }

                // Don't add button if already exists
                if (pre.querySelector('.copy-code-button')) return;

                const button = document.createElement('button');
                button.className = 'copy-code-button';
                button.textContent = 'Copy';
                button.addEventListener('click', async () => {
                    try {
                        await navigator.clipboard.writeText(codeBlock.textContent);
                        button.textContent = 'Copied!';
                        button.classList.add('copied');
                        setTimeout(() => {
                            button.textContent = 'Copy';
                            button.classList.remove('copied');
                        }, 2000);
                    } catch (err) {
                        console.error('Failed to copy:', err);
                        button.textContent = 'Failed';
                        setTimeout(() => {
                            button.textContent = 'Copy';
                        }, 2000);
                    }
                });
                pre.appendChild(button);
            });
        }

        // Render all existing markdown content on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.markdown-content').forEach(element => {
                const rawContent = atob(element.dataset.rawContent);
                element.innerHTML = renderMarkdown(rawContent);
                addCopyButtons(element);
            });
        });

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

                const messageDiv = document.createElement('div');
                messageDiv.className = 'flex justify-start animate-fade-in';
                messageDiv.innerHTML = `
                    <div class="max-w-3xl w-full mr-12">
                        <div class="bg-gray-50 shadow-sm sm:rounded-lg p-6 border-2 border-green-200">
                            <div class="flex items-start justify-between mb-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Assistant <span class="ml-1 text-xs">●</span>
                                </span>
                                <span class="text-xs text-gray-500">${timestamp}</span>
                            </div>
                            <div class="markdown-content"></div>
                            ${queryStatus && queryStatus.duration_ms ? `
                                <div class="mt-3 pt-3 border-t border-gray-200 text-xs text-gray-500">
                                    Response time: ${(queryStatus.duration_ms / 1000).toFixed(2)}s
                                    ${queryStatus.reasoning_content ? ' • Includes reasoning' : ''}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;

                // Render markdown for the new message
                const markdownContainer = messageDiv.querySelector('.markdown-content');
                markdownContainer.innerHTML = renderMarkdown(message.content);
                addCopyButtons(markdownContainer);

                return messageDiv.outerHTML;
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

            // Add CSS for animations and markdown styling
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

                /* Markdown Content Styling */
                .markdown-content {
                    color: #1f2937;
                    font-size: 0.9375rem;
                    line-height: 1.7;
                }

                .markdown-content p {
                    margin-bottom: 1em;
                }

                .markdown-content p:last-child {
                    margin-bottom: 0;
                }

                .markdown-content h1, .markdown-content h2, .markdown-content h3,
                .markdown-content h4, .markdown-content h5, .markdown-content h6 {
                    font-weight: 600;
                    margin-top: 1.5em;
                    margin-bottom: 0.75em;
                    line-height: 1.3;
                    color: #111827;
                }

                .markdown-content h1 { font-size: 1.75em; }
                .markdown-content h2 { font-size: 1.5em; }
                .markdown-content h3 { font-size: 1.25em; }
                .markdown-content h4 { font-size: 1.125em; }

                .markdown-content h1:first-child, .markdown-content h2:first-child,
                .markdown-content h3:first-child, .markdown-content h4:first-child {
                    margin-top: 0;
                }

                .markdown-content code {
                    background-color: #f3f4f6;
                    padding: 0.125rem 0.375rem;
                    border-radius: 0.25rem;
                    font-family: 'Menlo', 'Monaco', 'Courier New', monospace;
                    font-size: 0.875em;
                    color: #be123c;
                }

                .markdown-content pre {
                    background-color: #1f2937;
                    color: #f3f4f6;
                    padding: 1rem;
                    border-radius: 0.5rem;
                    overflow-x: auto;
                    margin: 1em 0;
                    position: relative;
                }

                .markdown-content pre code {
                    background-color: transparent;
                    padding: 0;
                    color: #f3f4f6;
                    font-size: 0.875rem;
                }

                .copy-code-button {
                    position: absolute;
                    top: 0.5rem;
                    right: 0.5rem;
                    padding: 0.25rem 0.5rem;
                    background-color: #374151;
                    color: #9ca3af;
                    border: 1px solid #4b5563;
                    border-radius: 0.375rem;
                    font-size: 0.75rem;
                    cursor: pointer;
                    opacity: 0;
                    transition: all 0.2s;
                }

                .markdown-content pre:hover .copy-code-button {
                    opacity: 1;
                }

                .copy-code-button:hover {
                    background-color: #4b5563;
                    color: #f3f4f6;
                }

                .copy-code-button.copied {
                    background-color: #059669;
                    color: white;
                    border-color: #059669;
                }

                .markdown-content ul, .markdown-content ol {
                    margin: 1em 0;
                    padding-left: 2em;
                }

                .markdown-content li {
                    margin: 0.5em 0;
                }

                .markdown-content blockquote {
                    border-left: 4px solid #e5e7eb;
                    padding-left: 1rem;
                    margin: 1em 0;
                    color: #6b7280;
                    font-style: italic;
                }

                .markdown-content a {
                    color: #2563eb;
                    text-decoration: underline;
                }

                .markdown-content a:hover {
                    color: #1d4ed8;
                }

                .markdown-content table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 1em 0;
                }

                .markdown-content th, .markdown-content td {
                    border: 1px solid #e5e7eb;
                    padding: 0.5rem 0.75rem;
                    text-align: left;
                }

                .markdown-content th {
                    background-color: #f9fafb;
                    font-weight: 600;
                }

                .markdown-content strong {
                    font-weight: 600;
                    color: #111827;
                }

                .markdown-content em {
                    font-style: italic;
                }
            `;
            document.head.appendChild(style);
        });

        // Title editing functionality
        const titleDisplay = document.getElementById('titleDisplay');
        const titleForm = document.getElementById('titleForm');
        const titleInput = document.getElementById('titleInput');
        const cancelEdit = document.getElementById('cancelEdit');

        titleDisplay.addEventListener('click', () => {
            titleDisplay.classList.add('hidden');
            titleForm.classList.remove('hidden');
            titleInput.focus();
            titleInput.select();
        });

        cancelEdit.addEventListener('click', () => {
            titleForm.classList.add('hidden');
            titleDisplay.classList.remove('hidden');
            titleInput.value = titleDisplay.querySelector('#titleText').textContent.trim();
        });

        // Allow Escape to cancel editing
        titleInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                cancelEdit.click();
            }
        });

        // Form submission loading state
        const messageForm = document.getElementById('messageForm');
        const messageInput = document.getElementById('messageInput');
        const sendButton = document.getElementById('sendButton');
        const loadingSpinner = document.getElementById('loadingSpinner');
        const buttonText = document.getElementById('buttonText');

        if (messageForm) {
            messageForm.addEventListener('submit', function() {
                // Show loading state
                sendButton.disabled = true;
                messageInput.disabled = true;
                loadingSpinner.classList.remove('hidden');
                buttonText.textContent = 'Sending...';
            });
        }
    </script>
</x-app-layout>
