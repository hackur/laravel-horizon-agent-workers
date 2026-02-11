<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">🎭 Agent Runs (Orchestrator)</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-4">
                    <div class="text-2xl font-bold text-gray-900">{{ $stats['total'] }}</div>
                    <div class="text-sm text-gray-500">Total Runs</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-4">
                    <div class="text-2xl font-bold text-blue-600">{{ $stats['running'] }}</div>
                    <div class="text-sm text-gray-500">Running</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-4">
                    <div class="text-2xl font-bold text-green-600">{{ $stats['completed'] }}</div>
                    <div class="text-sm text-gray-500">Completed</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-4">
                    <div class="text-2xl font-bold text-red-600">{{ $stats['failed'] }}</div>
                    <div class="text-sm text-gray-500">Failed</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-4">
                    <div class="text-2xl font-bold text-purple-600">{{ $stats['avg_iterations'] }}</div>
                    <div class="text-sm text-gray-500">Avg Iterations</div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-between items-center mb-6">
                <div class="space-x-2">
                    <a href="{{ route('agent-runs.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        🚀 New Agent Run
                    </a>
                    <a href="/horizon" target="_blank" class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700">
                        📊 Horizon Dashboard
                    </a>
                </div>
                <div class="text-sm text-gray-500">
                    Auto-refresh: <span id="countdown">30</span>s
                </div>
            </div>

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Runs Table -->
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Task</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Iterations</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Models</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Duration</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($runs as $run)
                            <tr class="{{ $run->isRunning() ? 'bg-blue-50' : '' }}">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono">{{ $run->id }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <div class="max-w-xs truncate" title="{{ $run->task }}">
                                        {{ Str::limit($run->task, 60) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        {{ $run->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $run->status === 'running' ? 'bg-blue-100 text-blue-800 animate-pulse' : '' }}
                                        {{ $run->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}
                                        {{ $run->status === 'max_iterations_reached' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                                        @if($run->status === 'running')
                                            🔄
                                        @elseif($run->status === 'completed')
                                            ✅
                                        @elseif($run->status === 'failed')
                                            ❌
                                        @else
                                            ⚠️
                                        @endif
                                        {{ $run->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    {{ $run->iterations_used ?? '-' }} / {{ $run->max_iterations }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="text-gray-600">{{ $run->agent_model }}</span>
                                    <span class="text-gray-400">/</span>
                                    <span class="text-purple-600">{{ $run->reviewer_model }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($run->duration)
                                        {{ gmdate('i:s', $run->duration) }}
                                    @elseif($run->isRunning() && $run->started_at)
                                        <span class="text-blue-600">{{ gmdate('i:s', now()->diffInSeconds($run->started_at)) }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $run->created_at->diffForHumans() }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                                    <a href="{{ route('agent-runs.show', $run) }}" class="text-blue-600 hover:text-blue-900">View</a>
                                    @if($run->isRunning())
                                        <form action="{{ route('agent-runs.destroy', $run) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900" onclick="return confirm('Cancel this run?')">Cancel</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                    <div class="text-4xl mb-2">🎭</div>
                                    <p>No agent runs yet.</p>
                                    <a href="{{ route('agent-runs.create') }}" class="text-blue-600 hover:text-blue-900">Start your first orchestrated run!</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $runs->links() }}
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh for running jobs
        let countdown = 30;
        const countdownEl = document.getElementById('countdown');
        
        setInterval(() => {
            countdown--;
            if (countdownEl) countdownEl.textContent = countdown;
            if (countdown <= 0) {
                location.reload();
            }
        }, 1000);
    </script>
</x-app-layout>
