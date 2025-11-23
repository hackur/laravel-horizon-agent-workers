<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LLMQueryDispatcher;
use App\Services\ProviderHealthCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LLMQueryDispatcherHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected LLMQueryDispatcher $dispatcher;

    protected ProviderHealthCheck $healthCheck;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = app(LLMQueryDispatcher::class);
        $this->healthCheck = app(ProviderHealthCheck::class);
        Cache::flush();
        Queue::fake();
    }

    public function test_dispatch_succeeds_when_provider_is_healthy(): void
    {
        // Mock healthy provider
        Cache::put('provider_health:ollama', [
            'status' => 'healthy',
            'message' => 'Ollama is running',
        ], 60);

        $user = User::factory()->create();

        $query = $this->dispatcher->dispatch(
            'ollama',
            'Test prompt',
            'llama3.2',
            ['user_id' => $user->id]
        );

        $this->assertNotNull($query);
        $this->assertEquals('pending', $query->status);
        $this->assertEquals('ollama', $query->provider);
    }

    public function test_dispatch_allows_degraded_provider_with_warning(): void
    {
        // Mock degraded provider
        Cache::put('provider_health:ollama', [
            'status' => 'degraded',
            'message' => 'Ollama running but slow',
        ], 60);

        $user = User::factory()->create();

        $query = $this->dispatcher->dispatch(
            'ollama',
            'Test prompt',
            'llama3.2',
            ['user_id' => $user->id]
        );

        // Should still dispatch successfully
        $this->assertNotNull($query);
        $this->assertEquals('pending', $query->status);
    }

    public function test_dispatch_fails_when_provider_is_unhealthy(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('currently unavailable');

        // Mock unhealthy provider
        Cache::put('provider_health:ollama', [
            'status' => 'unhealthy',
            'message' => 'Cannot connect to Ollama',
        ], 60);

        $user = User::factory()->create();

        $this->dispatcher->dispatch(
            'ollama',
            'Test prompt',
            'llama3.2',
            ['user_id' => $user->id]
        );
    }

    public function test_can_bypass_health_check(): void
    {
        // Mock unhealthy provider
        Cache::put('provider_health:ollama', [
            'status' => 'unhealthy',
            'message' => 'Cannot connect to Ollama',
        ], 60);

        $user = User::factory()->create();

        // Should succeed with skip_health_check option
        $query = $this->dispatcher->dispatch(
            'ollama',
            'Test prompt',
            'llama3.2',
            [
                'user_id' => $user->id,
                'skip_health_check' => true,
            ]
        );

        $this->assertNotNull($query);
        $this->assertEquals('pending', $query->status);
    }

    public function test_can_allow_unhealthy_provider_explicitly(): void
    {
        // Mock unhealthy provider
        Cache::put('provider_health:ollama', [
            'status' => 'unhealthy',
            'message' => 'Cannot connect to Ollama',
        ], 60);

        $user = User::factory()->create();

        // Should succeed with allow_unhealthy option
        $query = $this->dispatcher->dispatch(
            'ollama',
            'Test prompt',
            'llama3.2',
            [
                'user_id' => $user->id,
                'allow_unhealthy' => true,
            ]
        );

        $this->assertNotNull($query);
        $this->assertEquals('pending', $query->status);
    }

    public function test_dispatch_with_fallback_succeeds(): void
    {
        // Mock primary provider as unhealthy
        Cache::put('provider_health:ollama', [
            'status' => 'unhealthy',
            'message' => 'Cannot connect to Ollama',
        ], 60);

        // Mock fallback provider as healthy
        Cache::put('provider_health:claude', [
            'status' => 'healthy',
            'message' => 'Claude API is accessible',
        ], 60);

        $user = User::factory()->create();

        $query = $this->dispatcher->dispatchWithFallback(
            'ollama',
            'Test prompt',
            null,
            ['user_id' => $user->id],
            ['claude', 'lmstudio']
        );

        $this->assertNotNull($query);
        $this->assertEquals('claude', $query->provider);
        $this->assertArrayHasKey('fallback_from', $query->metadata);
        $this->assertEquals('ollama', $query->metadata['fallback_from']);
    }

    public function test_dispatch_with_fallback_fails_when_no_healthy_providers(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no healthy fallback providers found');

        // Mock all providers as unhealthy
        Cache::put('provider_health:ollama', [
            'status' => 'unhealthy',
            'message' => 'Cannot connect to Ollama',
        ], 60);

        Cache::put('provider_health:claude', [
            'status' => 'unhealthy',
            'message' => 'Invalid API key',
        ], 60);

        $user = User::factory()->create();

        $this->dispatcher->dispatchWithFallback(
            'ollama',
            'Test prompt',
            null,
            ['user_id' => $user->id],
            ['claude']
        );
    }

    public function test_can_find_healthy_provider(): void
    {
        // Mock different provider states
        Cache::put('provider_health:ollama', [
            'status' => 'unhealthy',
            'message' => 'Cannot connect',
        ], 60);

        Cache::put('provider_health:claude', [
            'status' => 'healthy',
            'message' => 'API accessible',
        ], 60);

        $healthyProvider = $this->dispatcher->findHealthyProvider(['ollama', 'claude', 'lmstudio']);

        $this->assertEquals('claude', $healthyProvider);
    }

    public function test_find_healthy_provider_returns_null_when_none_available(): void
    {
        // Mock all as unhealthy
        Cache::put('provider_health:ollama', [
            'status' => 'unhealthy',
            'message' => 'Cannot connect',
        ], 60);

        Cache::put('provider_health:claude', [
            'status' => 'unhealthy',
            'message' => 'Invalid API key',
        ], 60);

        $healthyProvider = $this->dispatcher->findHealthyProvider(['ollama', 'claude']);

        $this->assertNull($healthyProvider);
    }

    public function test_get_providers_with_health(): void
    {
        $providers = $this->dispatcher->getProvidersWithHealth();

        $this->assertArrayHasKey('claude', $providers);
        $this->assertArrayHasKey('ollama', $providers);

        foreach ($providers as $provider) {
            $this->assertArrayHasKey('health', $provider);
            $this->assertArrayHasKey('status', $provider['health']);
        }
    }

    public function test_dispatch_only_also_checks_health(): void
    {
        $this->expectException(\RuntimeException::class);

        // Mock unhealthy provider
        Cache::put('provider_health:ollama', [
            'status' => 'unhealthy',
            'message' => 'Cannot connect',
        ], 60);

        $this->dispatcher->dispatchOnly(
            'ollama',
            'Test prompt',
            'llama3.2',
            []
        );
    }
}
