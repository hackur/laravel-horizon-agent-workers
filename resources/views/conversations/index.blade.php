<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('My Conversations') }}
            </h2>
            <a href="{{ route('conversations.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                New Conversation
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Search and Filter -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
                <form method="GET" action="{{ route('conversations.index') }}" class="flex gap-4">
                    <div class="flex-1">
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Search conversations..."
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    </div>
                    <div>
                        <select name="provider" class="border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                            <option value="">All Providers</option>
                            @foreach($providers as $key => $provider)
                                <option value="{{ $key }}" {{ request('provider') === $key ? 'selected' : '' }}>
                                    {{ $provider['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                        Filter
                    </button>
                    @if(request()->hasAny(['search', 'provider']))
                        <a href="{{ route('conversations.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded">
                            Clear
                        </a>
                    @endif
                </form>
            </div>

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            @if($conversations->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <h3 class="mt-2 text-lg font-medium text-gray-900">No conversations yet</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by creating your first conversation.</p>
                    <div class="mt-6">
                        <a href="{{ route('conversations.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            Start a Conversation
                        </a>
                    </div>
                </div>
            @else
                <!-- Conversations Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($conversations as $conversation)
                        <a href="{{ route('conversations.show', $conversation) }}"
                           class="bg-white shadow-sm hover:shadow-md transition-shadow sm:rounded-lg p-6 block">
                            <div class="flex justify-between items-start mb-3">
                                <h3 class="text-lg font-semibold text-gray-900 line-clamp-1">
                                    {{ $conversation->title }}
                                </h3>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ $conversation->messages_count }}
                                </span>
                            </div>

                            <!-- Last Message Preview -->
                            @if($conversation->messages->isNotEmpty())
                                <p class="text-sm text-gray-600 mb-3 line-clamp-2">
                                    {{ $conversation->messages->first()->content }}
                                </p>
                            @endif

                            <!-- Provider Badge -->
                            <div class="flex items-center justify-between text-xs text-gray-500">
                                <div class="flex items-center space-x-2">
                                    <span class="inline-flex items-center px-2 py-1 rounded bg-gray-100 text-gray-700 font-medium">
                                        {{ $providers[$conversation->provider]['name'] ?? $conversation->provider }}
                                    </span>
                                    @if($conversation->model)
                                        <span class="text-gray-500">{{ $conversation->model }}</span>
                                    @endif
                                </div>
                            </div>

                            <!-- Timestamp -->
                            <div class="mt-3 text-xs text-gray-400">
                                {{ $conversation->last_message_at?->diffForHumans() ?? $conversation->updated_at->diffForHumans() }}
                            </div>
                        </a>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="mt-6">
                    {{ $conversations->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
