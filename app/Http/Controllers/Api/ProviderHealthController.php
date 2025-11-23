<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProviderHealthCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderHealthController extends Controller
{
    public function __construct(
        protected ProviderHealthCheck $healthCheck
    ) {}

    /**
     * Get health status for all providers.
     */
    public function index(Request $request): JsonResponse
    {
        $useCache = $request->boolean('cache', true);

        $statuses = $useCache
            ? $this->healthCheck->checkAllCached()
            : $this->healthCheck->checkAll();

        // Calculate overall system health
        $overallStatus = $this->calculateOverallStatus($statuses);

        return response()->json([
            'overall_status' => $overallStatus,
            'providers' => $statuses,
            'cached' => $useCache,
            'cache_ttl' => $this->healthCheck->getCacheTtl(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get health status for a specific provider.
     */
    public function show(Request $request, string $provider): JsonResponse
    {
        $useCache = $request->boolean('cache', true);

        $status = $useCache
            ? $this->healthCheck->checkCached($provider)
            : $this->healthCheck->check($provider);

        // Check if this is an unknown provider
        if ($status['status'] === 'unhealthy' &&
            $status['message'] === 'Unknown provider' &&
            isset($status['details']['provider'])) {
            return response()->json([
                'error' => 'Unknown provider',
                'message' => "Provider '{$provider}' not found",
                'available_providers' => $status['details']['available_providers'] ?? [],
            ], 404);
        }

        return response()->json([
            'provider' => $provider,
            'health' => $status,
            'cached' => $useCache,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Clear health check cache for all or specific provider.
     */
    public function clearCache(Request $request): JsonResponse
    {
        $provider = $request->input('provider');

        $this->healthCheck->clearCache($provider);

        return response()->json([
            'message' => $provider
                ? "Cache cleared for provider: {$provider}"
                : 'Cache cleared for all providers',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get summary of provider statuses.
     */
    public function summary(): JsonResponse
    {
        $statuses = $this->healthCheck->checkAllCached();

        $summary = [
            'healthy' => [],
            'degraded' => [],
            'unhealthy' => [],
        ];

        foreach ($statuses as $provider => $status) {
            $summary[$status['status']][] = $provider;
        }

        return response()->json([
            'summary' => $summary,
            'counts' => [
                'healthy' => count($summary['healthy']),
                'degraded' => count($summary['degraded']),
                'unhealthy' => count($summary['unhealthy']),
                'total' => 4,
            ],
            'overall_status' => $this->calculateOverallStatus($statuses),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Calculate overall system status from provider statuses.
     */
    protected function calculateOverallStatus(array $statuses): string
    {
        $hasHealthy = false;
        $hasDegraded = false;
        $hasUnhealthy = false;

        foreach ($statuses as $status) {
            match ($status['status']) {
                'healthy' => $hasHealthy = true,
                'degraded' => $hasDegraded = true,
                'unhealthy' => $hasUnhealthy = true,
                default => null,
            };
        }

        // If all are unhealthy
        if ($hasUnhealthy && ! $hasHealthy && ! $hasDegraded) {
            return 'critical';
        }

        // If any are healthy
        if ($hasHealthy) {
            return $hasDegraded || $hasUnhealthy ? 'degraded' : 'healthy';
        }

        // All degraded
        if ($hasDegraded) {
            return 'degraded';
        }

        return 'unknown';
    }
}
