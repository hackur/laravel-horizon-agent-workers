<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Dashboard') }}
            </h2>
            <a href="{{ route('conversations.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                New Conversation
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                @php
                    $totalConversations = auth()->user()->conversations()->count();
                    $totalQueries = auth()->user()->llmQueries()->count();
                    $completedQueries = auth()->user()->llmQueries()->where('status', 'completed')->count();
                    $pendingQueries = auth()->user()->llmQueries()->whereIn('status', ['pending', 'processing'])->count();
                @endphp

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 mb-1">Total Conversations</div>
                    <div class="text-3xl font-bold text-gray-900">{{ $totalConversations }}</div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 mb-1">Total Queries</div>
                    <div class="text-3xl font-bold text-gray-900">{{ $totalQueries }}</div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 mb-1">Completed</div>
                    <div class="text-3xl font-bold text-green-600">{{ $completedQueries }}</div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 mb-1">In Progress</div>
                    <div class="text-3xl font-bold text-yellow-600">{{ $pendingQueries }}</div>
                </div>
            </div>

            <!-- Recent Conversations -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Conversations</h3>
                    <a href="{{ route('conversations.index') }}" class="text-blue-600 hover:text-blue-900 text-sm font-medium">
                        View All →
                    </a>
                </div>

                @php
                    $recentConversations = auth()->user()
                        ->conversations()
                        ->with(['messages' => fn($q) => $q->latest()->limit(1)])
                        ->withCount('messages')
                        ->latest('last_message_at')
                        ->limit(5)
                        ->get();
                @endphp

                @if($recentConversations->isEmpty())
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No conversations yet</h3>
                        <p class="mt-1 text-sm text-gray-500">Get started by creating your first conversation.</p>
                        <div class="mt-6">
                            <a href="{{ route('conversations.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                Start a Conversation
                            </a>
                        </div>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach($recentConversations as $conversation)
                            <a href="{{ route('conversations.show', $conversation) }}"
                               class="block p-4 border border-gray-200 rounded-lg hover:border-blue-500 hover:shadow-md transition">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center space-x-2 mb-2">
                                            <h4 class="text-sm font-medium text-gray-900 truncate">
                                                {{ $conversation->title }}
                                            </h4>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                {{ $conversation->messages_count }}
                                            </span>
                                        </div>

                                        @if($conversation->messages->isNotEmpty())
                                            <p class="text-sm text-gray-600 line-clamp-1">
                                                {{ $conversation->messages->first()->content }}
                                            </p>
                                        @endif

                                        <div class="flex items-center space-x-3 mt-2 text-xs text-gray-500">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100">
                                                {{ ucfirst($conversation->provider) }}
                                            </span>
                                            @if($conversation->model)
                                                <span>{{ $conversation->model }}</span>
                                            @endif
                                            <span>{{ $conversation->last_message_at?->diffForHumans() ?? $conversation->updated_at->diffForHumans() }}</span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Quick Actions -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <a href="{{ route('conversations.create') }}" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 hover:shadow-md transition">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-900">New Conversation</h3>
                            <p class="text-sm text-gray-500">Start chatting with AI</p>
                        </div>
                    </div>
                </a>

                <a href="{{ route('llm-queries.create') }}" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 hover:shadow-md transition">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-900">One-time Query</h3>
                            <p class="text-sm text-gray-500">Single LLM request</p>
                        </div>
                    </div>
                </a>

                <a href="/horizon" target="_blank" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 hover:shadow-md transition">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-900">Horizon Dashboard</h3>
                            <p class="text-sm text-gray-500">Monitor queue jobs</p>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
