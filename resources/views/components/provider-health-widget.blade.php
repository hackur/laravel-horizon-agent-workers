<div x-data="providerHealthWidget()" x-init="init()" class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
    <div class="p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Provider Health Status</h3>
            <button
                @click="refresh()"
                :disabled="loading"
                class="text-sm text-indigo-600 hover:text-indigo-800 disabled:text-gray-400 disabled:cursor-not-allowed"
            >
                <svg class="w-5 h-5" :class="{ 'animate-spin': loading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
            </button>
        </div>

        <div x-show="error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md">
            <p class="text-sm text-red-800" x-text="error"></p>
        </div>

        <!-- Overall Status -->
        <div class="mb-4 p-4 rounded-lg" :class="{
            'bg-green-50 border border-green-200': overallStatus === 'healthy',
            'bg-yellow-50 border border-yellow-200': overallStatus === 'degraded',
            'bg-red-50 border border-red-200': overallStatus === 'critical',
            'bg-gray-50 border border-gray-200': overallStatus === 'unknown'
        }">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" :class="{
                    'text-green-500': overallStatus === 'healthy',
                    'text-yellow-500': overallStatus === 'degraded',
                    'text-red-500': overallStatus === 'critical',
                    'text-gray-500': overallStatus === 'unknown'
                }" fill="currentColor" viewBox="0 0 20 20">
                    <circle cx="10" cy="10" r="8"></circle>
                </svg>
                <span class="font-semibold" :class="{
                    'text-green-800': overallStatus === 'healthy',
                    'text-yellow-800': overallStatus === 'degraded',
                    'text-red-800': overallStatus === 'critical',
                    'text-gray-800': overallStatus === 'unknown'
                }" x-text="'System Status: ' + (overallStatus || 'Loading...').toUpperCase()"></span>
            </div>
        </div>

        <!-- Provider List -->
        <div class="space-y-3">
            <template x-for="provider in providers" :key="provider.key">
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <svg class="w-4 h-4 mr-2" :class="{
                                    'text-green-500': provider.health?.status === 'healthy',
                                    'text-yellow-500': provider.health?.status === 'degraded',
                                    'text-red-500': provider.health?.status === 'unhealthy',
                                    'text-gray-400': !provider.health || provider.health.status === 'unknown'
                                }" fill="currentColor" viewBox="0 0 20 20">
                                    <circle cx="10" cy="10" r="6"></circle>
                                </svg>
                                <h4 class="font-medium text-gray-900" x-text="provider.name"></h4>
                            </div>
                            <p class="text-sm text-gray-600 mb-2" x-text="provider.description"></p>

                            <template x-if="provider.health">
                                <div>
                                    <p class="text-sm" :class="{
                                        'text-green-700': provider.health.status === 'healthy',
                                        'text-yellow-700': provider.health.status === 'degraded',
                                        'text-red-700': provider.health.status === 'unhealthy',
                                        'text-gray-600': provider.health.status === 'unknown'
                                    }" x-text="provider.health.message"></p>

                                    <!-- Additional details -->
                                    <template x-if="provider.health.details">
                                        <div class="mt-2 text-xs text-gray-500">
                                            <template x-if="provider.health.details.models">
                                                <div>
                                                    <strong>Models:</strong> <span x-text="provider.health.details.models.join(', ')"></span>
                                                </div>
                                            </template>
                                            <template x-if="provider.health.details.available_tools">
                                                <div>
                                                    <strong>Tools:</strong> <span x-text="provider.health.details.available_tools.join(', ')"></span>
                                                </div>
                                            </template>
                                            <template x-if="provider.health.details.message">
                                                <div class="mt-1 italic" x-text="provider.health.details.message"></div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

                        <!-- Status Badge -->
                        <span class="ml-4 px-2 py-1 text-xs font-semibold rounded-full whitespace-nowrap" :class="{
                            'bg-green-100 text-green-800': provider.health?.status === 'healthy',
                            'bg-yellow-100 text-yellow-800': provider.health?.status === 'degraded',
                            'bg-red-100 text-red-800': provider.health?.status === 'unhealthy',
                            'bg-gray-100 text-gray-800': !provider.health || provider.health.status === 'unknown'
                        }" x-text="(provider.health?.status || 'unknown').toUpperCase()"></span>
                    </div>
                </div>
            </template>
        </div>

        <!-- Last Updated -->
        <div class="mt-4 text-xs text-gray-500 text-center">
            <span x-show="lastUpdated">
                Last updated: <span x-text="lastUpdated"></span>
            </span>
            <span x-show="cached" class="ml-2">(cached)</span>
        </div>
    </div>
</div>

<script>
function providerHealthWidget() {
    return {
        providers: [],
        overallStatus: 'unknown',
        loading: false,
        error: null,
        lastUpdated: null,
        cached: false,
        autoRefreshInterval: null,

        init() {
            this.fetchStatus();
            // Auto-refresh every 60 seconds
            this.autoRefreshInterval = setInterval(() => this.fetchStatus(), 60000);
        },

        async fetchStatus(useCache = true) {
            this.loading = true;
            this.error = null;

            try {
                const response = await fetch(`/api/providers/health?cache=${useCache ? '1' : '0'}`);

                if (!response.ok) {
                    throw new Error('Failed to fetch provider health status');
                }

                const data = await response.json();

                this.overallStatus = data.overall_status;
                this.cached = data.cached;
                this.lastUpdated = new Date(data.timestamp).toLocaleTimeString();

                // Transform providers data
                this.providers = Object.entries(data.providers).map(([key, health]) => {
                    const providerInfo = this.getProviderInfo(key);
                    return {
                        key,
                        name: providerInfo.name,
                        description: providerInfo.description,
                        health: health
                    };
                });
            } catch (err) {
                this.error = err.message;
                console.error('Provider health check error:', err);
            } finally {
                this.loading = false;
            }
        },

        async refresh() {
            // Force refresh (bypass cache)
            await this.fetchStatus(false);
        },

        getProviderInfo(key) {
            const providers = {
                'claude': {
                    name: 'Claude API',
                    description: 'Anthropic Claude API'
                },
                'ollama': {
                    name: 'Ollama',
                    description: 'Local Ollama instance'
                },
                'lmstudio': {
                    name: 'LM Studio',
                    description: 'Local LM Studio server'
                },
                'local-command': {
                    name: 'Local Command',
                    description: 'Local CLI tools'
                }
            };
            return providers[key] || { name: key, description: 'Unknown provider' };
        },

        destroy() {
            if (this.autoRefreshInterval) {
                clearInterval(this.autoRefreshInterval);
            }
        }
    }
}
</script>
