<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Query #{{ $query->id }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta http-equiv="refresh" content="5" id="autoRefresh">
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
            </dl>
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

        @if($query->error)
        <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-6">
            <h2 class="text-xl font-bold text-red-800 mb-4">Error</h2>
            <div class="bg-white p-4 rounded border border-red-300">
                <pre class="whitespace-pre-wrap text-sm text-red-700">{{ $query->error }}</pre>
            </div>
        </div>
        @endif

        @if(in_array($query->status, ['pending', 'processing']))
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
            <p class="text-blue-800">This page will auto-refresh every 5 seconds until the query is complete.</p>
            <button onclick="document.getElementById('autoRefresh').remove()" class="mt-2 text-blue-600 hover:text-blue-900 text-sm underline">
                Disable auto-refresh
            </button>
        </div>
        @else
        <script>
            document.getElementById('autoRefresh')?.remove();
        </script>
        @endif
    </div>
</body>
</html>
