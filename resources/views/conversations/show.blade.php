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

                <!-- Export Dropdown -->
                <div class="relative inline-block text-left" x-data="{ open: false }">
                    <button @click="open = !open" @click.away="open = false"
                            type="button"
                            class="inline-flex items-center text-xs px-3 py-1 bg-green-100 text-green-700 rounded-full hover:bg-green-200 transition">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Export
                        <svg class="w-3 h-3 ml-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                    </button>

                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="transform opacity-0 scale-95"
                         x-transition:enter-end="transform opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="transform opacity-100 scale-100"
                         x-transition:leave-end="transform opacity-0 scale-95"
                         class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10"
                         style="display: none;">
                        <div class="py-1">
                            <a href="{{ route('conversations.export.json', $conversation) }}"
                               class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                                Export as JSON
                            </a>
                            <a href="{{ route('conversations.export.markdown', $conversation) }}"
                               class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Export as Markdown
                            </a>
                        </div>
                    </div>
                </div>

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

            <!-- Token Usage Panel -->
            @if(isset($tokenInfo))
                <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path>
                            </svg>
                            Context Token Usage
                        </h3>

                        <!-- Token Usage Bar -->
                        <div class="mb-4">
                            <div class="flex justify-between text-sm mb-2">
                                <span class="font-medium">
                                    {{ number_format($tokenInfo['current_tokens']) }} / {{ number_format($tokenInfo['safe_limit']) }} tokens
                                    ({{ $tokenInfo['usage_percent'] }}%)
                                </span>
                                <span class="text-gray-600">
                                    {{ number_format($tokenInfo['remaining_tokens']) }} remaining
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                                @php
                                    $barColor = match($tokenInfo['warning_level']) {
                                        'exceeded' => 'bg-red-600',
                                        'critical' => 'bg-red-500',
                                        'warning' => 'bg-yellow-500',
                                        default => 'bg-green-500'
                                    };
                                    $barWidth = min(100, $tokenInfo['usage_percent']);
                                @endphp
                                <div class="{{ $barColor }} h-3 transition-all duration-300" style="width: {{ $barWidth }}%"></div>
                            </div>
                        </div>

                        <!-- Warning Messages -->
                        @if($tokenInfo['warning_level'] === 'exceeded')
                            <div class="bg-red-50 border-l-4 border-red-400 p-3 mb-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-4 w-4 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-xs text-red-700 font-semibold">
                                            Context limit exceeded! Older messages have been truncated.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @elseif($tokenInfo['warning_level'] === 'critical')
                            <div class="bg-red-50 border-l-4 border-red-400 p-3 mb-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-4 w-4 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-xs text-red-700 font-semibold">
                                            Critical: Context nearly full ({{ $tokenInfo['usage_percent'] }}%). Future messages may be truncated.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @elseif($tokenInfo['warning_level'] === 'warning')
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-3 mb-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-4 w-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-xs text-yellow-700">
                                            Warning: Context approaching limit ({{ $tokenInfo['usage_percent'] }}%). Consider starting a new conversation soon.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($tokenInfo['was_truncated'])
                            <div class="bg-blue-50 border-l-4 border-blue-400 p-3 mb-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-4 w-4 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-xs text-blue-700">
                                            {{ $tokenInfo['messages_removed'] }} older {{ $tokenInfo['messages_removed'] === 1 ? 'message' : 'messages' }} truncated to fit context window. Showing {{ $tokenInfo['messages_count'] }} of {{ $tokenInfo['original_messages_count'] }} total messages.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Token Details -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                            <div class="bg-gray-50 rounded p-2">
                                <div class="text-gray-500 uppercase tracking-wide">Model</div>
                                <div class="font-semibold text-gray-900 mt-1">{{ $tokenInfo['model_display_name'] }}</div>
                            </div>
                            <div class="bg-gray-50 rounded p-2">
                                <div class="text-gray-500 uppercase tracking-wide">Messages</div>
                                <div class="font-semibold text-gray-900 mt-1">{{ number_format($tokenInfo['messages_count']) }}</div>
                            </div>
                            <div class="bg-gray-50 rounded p-2">
                                <div class="text-gray-500 uppercase tracking-wide">Full Limit</div>
                                <div class="font-semibold text-gray-900 mt-1">{{ number_format($tokenInfo['full_limit']) }}</div>
                            </div>
                            <div class="bg-gray-50 rounded p-2">
                                <div class="text-gray-500 uppercase tracking-wide">Safe Limit</div>
                                <div class="font-semibold text-gray-900 mt-1">{{ number_format($tokenInfo['safe_limit']) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Conversation Statistics Panel -->
            @if(isset($statistics) && $statistics['total_queries'] > 0)
                <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            Conversation Statistics
                        </h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="text-xs text-gray-500 uppercase tracking-wide">Total Queries</div>
                                <div class="text-2xl font-semibold text-gray-900 mt-1">{{ number_format($statistics['total_queries']) }}</div>
                            </div>
                            @if($statistics['total_tokens'] > 0)
                                <div class="bg-purple-50 rounded-lg p-4">
                                    <div class="text-xs text-purple-600 uppercase tracking-wide">Total Tokens</div>
                                    <div class="text-2xl font-semibold text-purple-900 mt-1">{{ number_format($statistics['total_tokens']) }}</div>
                                    <div class="text-xs text-purple-600 mt-1">
                                        In: {{ number_format($statistics['input_tokens']) }} / Out: {{ number_format($statistics['output_tokens']) }}
                                    </div>
                                </div>
                            @endif
                            @if($statistics['total_cost_usd'] > 0)
                                <div class="bg-green-50 rounded-lg p-4">
                                    <div class="text-xs text-green-600 uppercase tracking-wide">Total Cost</div>
                                    <div class="text-2xl font-semibold text-green-900 mt-1">${{ number_format($statistics['total_cost_usd'], 4) }}</div>
                                    @if($statistics['avg_cost_usd'] > 0)
                                        <div class="text-xs text-green-600 mt-1">
                                            Avg: ${{ number_format($statistics['avg_cost_usd'], 4) }}
                                        </div>
                                    @endif
                                </div>
                            @endif
                            @if($statistics['avg_duration_ms'] > 0)
                                <div class="bg-blue-50 rounded-lg p-4">
                                    <div class="text-xs text-blue-600 uppercase tracking-wide">Avg Response Time</div>
                                    <div class="text-2xl font-semibold text-blue-900 mt-1">{{ number_format($statistics['avg_duration_ms'] / 1000, 2) }}s</div>
                                </div>
                            @endif
                        </div>
                        @if($statistics['over_budget_count'] > 0)
                            <div class="mt-4 bg-yellow-50 border-l-4 border-yellow-400 p-3">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-4 w-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-xs text-yellow-700">
                                            {{ $statistics['over_budget_count'] }} {{ $statistics['over_budget_count'] === 1 ? 'query' : 'queries' }} exceeded the budget limit.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
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
                                                <div class="flex flex-wrap gap-2">
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
                                                    @if($message->llmQuery->cost_usd)
                                                        <span class="inline-flex items-center px-2 py-1 bg-green-50 text-green-700 rounded">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                            </svg>
                                                            ${{ number_format($message->llmQuery->cost_usd, 4) }}
                                                        </span>
                                                    @endif
                                                    @if($message->llmQuery->pricing_tier)
                                                        <span class="inline-flex items-center px-2 py-1
                                                            {{ $message->llmQuery->pricing_tier === 'opus' ? 'bg-purple-50 text-purple-700' : '' }}
                                                            {{ $message->llmQuery->pricing_tier === 'sonnet' ? 'bg-blue-50 text-blue-700' : '' }}
                                                            {{ $message->llmQuery->pricing_tier === 'haiku' ? 'bg-teal-50 text-teal-700' : '' }}
                                                            rounded">
                                                            {{ ucfirst($message->llmQuery->pricing_tier) }}
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

    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/conversation-show.js'])

    <script type="module">
        import { initConversationShow } from '/resources/js/conversation-show.js';

        // Initialize the conversation show page
        document.addEventListener('DOMContentLoaded', () => {
            initConversationShow({{ $conversation->id }});
        });
    </script>
</x-app-layout>
