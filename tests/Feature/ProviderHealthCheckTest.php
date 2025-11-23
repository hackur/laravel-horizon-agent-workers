<?php

namespace Tests\Feature;

use App\Services\ProviderHealthCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected ProviderHealthCheck $healthCheck;

    protected function setUp(): void
    {
        parent::setUp();
        $this->healthCheck = app(ProviderHealthCheck::class);
        Cache::flush();
    }

    public function test_can_check_all_providers(): void
    {
        $statuses = $this->healthCheck->checkAll();

        $this->assertArrayHasKey('claude', $statuses);
        $this->assertArrayHasKey('ollama', $statuses);
        $this->assertArrayHasKey('lmstudio', $statuses);
        $this->assertArrayHasKey('local-command', $statuses);

        foreach ($statuses as $status) {
            $this->assertArrayHasKey('status', $status);
            $this->assertArrayHasKey('message', $status);
            $this->assertArrayHasKey('timestamp', $status);
            $this->assertContains($status['status'], ['healthy', 'degraded', 'unhealthy', 'unknown']);
        }
    }

    public function test_can_check_individual_provider(): void
    {
        $status = $this->healthCheck->check('ollama');

        $this->assertArrayHasKey('status', $status);
        $this->assertArrayHasKey('message', $status);
        $this->assertArrayHasKey('timestamp', $status);
    }

    public function test_ollama_unhealthy_when_not_running(): void
    {
        Http::fake([
            '*/api/tags' => Http::response(null, 500),
        ]);

        $status = $this->healthCheck->check('ollama');

        $this->assertEquals('unhealthy', $status['status']);
        $this->assertStringContainsString('not responding', $status['message']);
    }

    public function test_lmstudio_unhealthy_when_not_running(): void
    {
        Http::fake([
            '*/models' => Http::response(null, 500),
        ]);

        $status = $this->healthCheck->check('lmstudio');

        $this->assertEquals('unhealthy', $status['status']);
        $this->assertStringContainsString('not responding', $status['message']);
    }

    public function test_health_check_caching(): void
    {
        Http::fake([
            '*/api/tags' => Http::response(['models' => [['name' => 'test-model']]], 200),
        ]);

        // First call should hit the API
        $status1 = $this->healthCheck->checkCached('ollama');
        $this->assertArrayHasKey('status', $status1);

        // Second call should use cache (won't hit HTTP fake again)
        $status2 = $this->healthCheck->checkCached('ollama');
        $this->assertEquals($status1, $status2);

        // Verify cache was used
        $this->assertTrue(Cache::has('provider_health:ollama'));
    }

    public function test_can_clear_health_check_cache(): void
    {
        $this->healthCheck->checkCached('ollama');
        $this->assertTrue(Cache::has('provider_health:ollama'));

        $this->healthCheck->clearCache('ollama');
        $this->assertFalse(Cache::has('provider_health:ollama'));
    }

    public function test_can_clear_all_health_check_caches(): void
    {
        $this->healthCheck->checkAllCached();

        $this->assertTrue(Cache::has('provider_health:claude'));
        $this->assertTrue(Cache::has('provider_health:ollama'));

        $this->healthCheck->clearCache();

        $this->assertFalse(Cache::has('provider_health:claude'));
        $this->assertFalse(Cache::has('provider_health:ollama'));
    }

    public function test_is_healthy_returns_correct_status(): void
    {
        Http::fake([
            '*/api/tags' => Http::response(['models' => [['name' => 'test']]], 200),
        ]);

        // Mock a healthy response
        Cache::put('provider_health:ollama', [
            'status' => 'healthy',
            'message' => 'Ollama is running',
        ], 60);

        $this->assertTrue($this->healthCheck->isHealthy('ollama'));

        // Mock an unhealthy response
        Cache::put('provider_health:ollama', [
            'status' => 'unhealthy',
            'message' => 'Ollama not responding',
        ], 60);

        $this->assertFalse($this->healthCheck->isHealthy('ollama'));
    }

    public function test_local_command_checks_shell_availability(): void
    {
        $status = $this->healthCheck->check('local-command');

        $this->assertArrayHasKey('status', $status);
        // Should at least detect that shell exists
        $this->assertNotEquals('unknown', $status['status']);
    }

    public function test_can_customize_cache_ttl(): void
    {
        $this->assertEquals(60, $this->healthCheck->getCacheTtl());

        $this->healthCheck->setCacheTtl(120);

        $this->assertEquals(120, $this->healthCheck->getCacheTtl());
    }

    public function test_can_customize_timeout(): void
    {
        $this->assertEquals(5, $this->healthCheck->getTimeout());

        $this->healthCheck->setTimeout(10);

        $this->assertEquals(10, $this->healthCheck->getTimeout());
    }
}
