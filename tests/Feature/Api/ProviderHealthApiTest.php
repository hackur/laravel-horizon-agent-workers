<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProviderHealthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_can_get_all_provider_health_status(): void
    {
        $response = $this->getJson('/api/providers/health');

        $response->assertOk()
            ->assertJsonStructure([
                'overall_status',
                'providers' => [
                    'claude' => ['status', 'message', 'timestamp'],
                    'ollama' => ['status', 'message', 'timestamp'],
                    'lmstudio' => ['status', 'message', 'timestamp'],
                    'local-command' => ['status', 'message', 'timestamp'],
                ],
                'cached',
                'cache_ttl',
                'timestamp',
            ]);

        $this->assertContains($response->json('overall_status'), [
            'healthy',
            'degraded',
            'critical',
            'unknown',
        ]);
    }

    public function test_can_get_specific_provider_health(): void
    {
        $response = $this->getJson('/api/providers/health/ollama');

        $response->assertOk()
            ->assertJsonStructure([
                'provider',
                'health' => ['status', 'message', 'timestamp'],
                'cached',
                'timestamp',
            ]);

        $this->assertEquals('ollama', $response->json('provider'));
    }

    public function test_returns_404_for_unknown_provider(): void
    {
        $response = $this->getJson('/api/providers/health/nonexistent');

        $response->assertNotFound()
            ->assertJson([
                'error' => 'Unknown provider',
            ]);
    }

    public function test_can_get_provider_health_summary(): void
    {
        $response = $this->getJson('/api/providers/health/summary');

        $response->assertOk()
            ->assertJsonStructure([
                'summary' => [
                    'healthy',
                    'degraded',
                    'unhealthy',
                ],
                'counts' => [
                    'healthy',
                    'degraded',
                    'unhealthy',
                    'total',
                ],
                'overall_status',
                'timestamp',
            ]);

        $this->assertEquals(4, $response->json('counts.total'));
    }

    public function test_can_bypass_cache(): void
    {
        // First request with cache
        $response1 = $this->getJson('/api/providers/health?cache=1');
        $response1->assertOk();
        $this->assertTrue($response1->json('cached'));

        // Second request without cache
        $response2 = $this->getJson('/api/providers/health?cache=0');
        $response2->assertOk();
        $this->assertFalse($response2->json('cached'));
    }

    public function test_authenticated_user_can_clear_cache(): void
    {
        $user = User::factory()->create();

        // Populate cache
        $this->getJson('/api/providers/health');
        $this->assertTrue(Cache::has('provider_health:claude'));

        // Clear cache
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/providers/health/clear-cache');

        $response->assertOk()
            ->assertJson([
                'message' => 'Cache cleared for all providers',
            ]);

        $this->assertFalse(Cache::has('provider_health:claude'));
    }

    public function test_can_clear_specific_provider_cache(): void
    {
        $user = User::factory()->create();

        // Populate cache
        $this->getJson('/api/providers/health');
        $this->assertTrue(Cache::has('provider_health:claude'));
        $this->assertTrue(Cache::has('provider_health:ollama'));

        // Clear only claude cache
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/providers/health/clear-cache', [
                'provider' => 'claude',
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Cache cleared for provider: claude',
            ]);

        $this->assertFalse(Cache::has('provider_health:claude'));
        $this->assertTrue(Cache::has('provider_health:ollama'));
    }

    public function test_guest_cannot_clear_cache(): void
    {
        $response = $this->postJson('/api/providers/health/clear-cache');

        $response->assertUnauthorized();
    }

    public function test_health_status_respects_rate_limiting(): void
    {
        // Make 61 requests (rate limit is 60 per minute for public routes)
        for ($i = 0; $i < 61; $i++) {
            $response = $this->getJson('/api/providers/health');

            if ($i < 60) {
                $response->assertOk();
            } else {
                $response->assertStatus(429); // Too Many Requests
            }
        }
    }

    public function test_health_check_returns_detailed_information(): void
    {
        $response = $this->getJson('/api/providers/health/local-command');

        $response->assertOk();

        $health = $response->json('health');
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('message', $health);
        $this->assertArrayHasKey('details', $health);
    }
}
