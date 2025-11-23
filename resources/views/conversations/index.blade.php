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
            <!-- Enhanced Search and Filter Panel -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
                <form method="GET" action="{{ route('conversations.index') }}" id="search-form">
                    <!-- Main Search Bar -->
                    <div class="mb-4">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <input type="text"
                                   name="search"
                                   value="{{ request('search') }}"
                                   placeholder="Search conversations and messages..."
                                   class="w-full pl-10 pr-4 py-3 border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                   autocomplete="off">
                            @if(request('search'))
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <button type="button" onclick="clearSearch()" class="text-gray-400 hover:text-gray-600">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                            @endif
                        </div>

                        <!-- Search Type Toggle -->
                        <div class="mt-2 flex items-center space-x-4 text-sm">
                            <label class="inline-flex items-center">
                                <input type="radio"
                                       name="search_type"
                                       value="content"
                                       {{ ($filters['search_type'] ?? 'content') === 'content' ? 'checked' : '' }}
                                       class="form-radio text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-gray-700">Search in messages</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio"
                                       name="search_type"
                                       value="title"
                                       {{ ($filters['search_type'] ?? 'content') === 'title' ? 'checked' : '' }}
                                       class="form-radio text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-gray-700">Search in titles only</span>
                            </label>
                        </div>
                    </div>

                    <!-- Advanced Filters (Collapsible) -->
                    <div id="advanced-filters" class="border-t pt-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-gray-700">Advanced Filters</h3>
                            <button type="button"
                                    onclick="toggleAdvancedFilters()"
                                    class="text-sm text-blue-600 hover:text-blue-800"
                                    id="toggle-filters-btn">
                                <span id="filters-toggle-text">Show</span>
                                <svg class="inline h-4 w-4 ml-1 transform transition-transform" id="filters-toggle-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                        </div>

                        <div id="advanced-filters-content" class="hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- Provider Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Provider</label>
                                <select name="provider" class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    <option value="">All Providers</option>
                                    @foreach($providers as $key => $provider)
                                        <option value="{{ $key }}" {{ ($filters['provider'] ?? '') === $key ? 'selected' : '' }}>
                                            {{ $provider['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Status Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select name="status" class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    <option value="">All Statuses</option>
                                    <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="processing" {{ ($filters['status'] ?? '') === 'processing' ? 'selected' : '' }}>Processing</option>
                                    <option value="completed" {{ ($filters['status'] ?? '') === 'completed' ? 'selected' : '' }}>Completed</option>
                                    <option value="failed" {{ ($filters['status'] ?? '') === 'failed' ? 'selected' : '' }}>Failed</option>
                                </select>
                            </div>

                            <!-- Date Range -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                                <input type="date"
                                       name="start_date"
                                       value="{{ $filters['start_date'] ?? '' }}"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                                <input type="date"
                                       name="end_date"
                                       value="{{ $filters['end_date'] ?? '' }}"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            </div>

                            <!-- Sort By -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                                <select name="sort_by" class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    <option value="recent" {{ ($filters['sort_by'] ?? 'recent') === 'recent' ? 'selected' : '' }}>Most Recent</option>
                                    <option value="oldest" {{ ($filters['sort_by'] ?? 'recent') === 'oldest' ? 'selected' : '' }}>Oldest First</option>
                                    <option value="title" {{ ($filters['sort_by'] ?? 'recent') === 'title' ? 'selected' : '' }}>Title A-Z</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="mt-4 flex gap-2">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md shadow-sm transition-colors">
                            Search
                        </button>
                        @if(request()->hasAny(['search', 'provider', 'status', 'start_date', 'end_date']))
                            <a href="{{ route('conversations.index') }}" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-2 rounded-md shadow-sm transition-colors">
                                Clear All
                            </a>
                        @endif
                    </div>
                </form>

                <!-- Active Filters Display -->
                @if(request()->hasAny(['search', 'provider', 'status', 'start_date', 'end_date']))
                    <div class="mt-4 pt-4 border-t">
                        <div class="flex flex-wrap gap-2 items-center">
                            <span class="text-sm font-medium text-gray-600">Active filters:</span>

                            @if(request('search'))
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800">
                                    <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                    "{{ request('search') }}"
                                </span>
                            @endif

                            @if(request('provider'))
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-green-100 text-green-800">
                                    Provider: {{ $providers[request('provider')]['name'] ?? request('provider') }}
                                </span>
                            @endif

                            @if(request('status'))
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-purple-100 text-purple-800">
                                    Status: {{ ucfirst(request('status')) }}
                                </span>
                            @endif

                            @if(request('start_date') || request('end_date'))
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-orange-100 text-orange-800">
                                    Date: {{ request('start_date', 'any') }} to {{ request('end_date', 'any') }}
                                </span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Search Results Info -->
            @if(request('search'))
                <div class="mb-4 text-sm text-gray-600">
                    Found <strong>{{ $conversations->total() }}</strong> conversation(s) matching your search
                </div>
            @endif

            @if($conversations->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <h3 class="mt-2 text-lg font-medium text-gray-900">
                        @if(request()->hasAny(['search', 'provider', 'status', 'start_date', 'end_date']))
                            No conversations found
                        @else
                            No conversations yet
                        @endif
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        @if(request()->hasAny(['search', 'provider', 'status', 'start_date', 'end_date']))
                            Try adjusting your search or filters.
                        @else
                            Get started by creating your first conversation.
                        @endif
                    </p>
                    <div class="mt-6">
                        @if(request()->hasAny(['search', 'provider', 'status', 'start_date', 'end_date']))
                            <a href="{{ route('conversations.index') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-gray-600 hover:bg-gray-700">
                                Clear Filters
                            </a>
                        @else
                            <a href="{{ route('conversations.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                Start a Conversation
                            </a>
                        @endif
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
                                    @if(isset($searchTerm) && $searchTerm)
                                        {!! $conversation->messages->first()?->highlightSearchTerm($conversation->title, $searchTerm) ?? $conversation->title !!}
                                    @else
                                        {{ $conversation->title }}
                                    @endif
                                </h3>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ $conversation->messages_count }}
                                </span>
                            </div>

                            <!-- Last Message Preview with Highlighting -->
                            @if($conversation->messages->isNotEmpty())
                                <p class="text-sm text-gray-600 mb-3 line-clamp-3">
                                    @if(isset($searchTerm) && $searchTerm)
                                        {!! $conversation->messages->first()->highlightSearchTerm(
                                            $conversation->messages->first()->getSearchExcerpt($searchTerm, 150),
                                            $searchTerm
                                        ) !!}
                                    @else
                                        {{ Str::limit($conversation->messages->first()->content, 150) }}
                                    @endif
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

    @push('scripts')
    <script>
        function clearSearch() {
            document.querySelector('input[name="search"]').value = '';
            document.getElementById('search-form').submit();
        }

        function toggleAdvancedFilters() {
            const content = document.getElementById('advanced-filters-content');
            const icon = document.getElementById('filters-toggle-icon');
            const text = document.getElementById('filters-toggle-text');

            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                icon.classList.add('rotate-180');
                text.textContent = 'Hide';
                localStorage.setItem('advancedFiltersOpen', 'true');
            } else {
                content.classList.add('hidden');
                icon.classList.remove('rotate-180');
                text.textContent = 'Show';
                localStorage.setItem('advancedFiltersOpen', 'false');
            }
        }

        // Restore advanced filters state from localStorage
        document.addEventListener('DOMContentLoaded', function() {
            const shouldShowFilters = localStorage.getItem('advancedFiltersOpen') === 'true'
                || {{ request()->hasAny(['provider', 'status', 'start_date', 'end_date', 'sort_by']) ? 'true' : 'false' }};

            if (shouldShowFilters) {
                toggleAdvancedFilters();
            }

            // Auto-submit on filter change (optional)
            const autoSubmitElements = document.querySelectorAll('select[name="provider"], select[name="status"], select[name="sort_by"]');
            autoSubmitElements.forEach(element => {
                element.addEventListener('change', function() {
                    if (this.value !== '' || document.querySelector('input[name="search"]').value !== '') {
                        document.getElementById('search-form').submit();
                    }
                });
            });
        });

        // Search on Enter key
        document.querySelector('input[name="search"]').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('search-form').submit();
            }
        });
    </script>
    @endpush
</x-app-layout>
