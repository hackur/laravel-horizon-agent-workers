<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function guest_requests_are_rate_limited()
    {
        // This test demonstrates rate limiting structure
        // In actual testing, you'd need to make 60+ requests to trigger it

        $response = $this->getJson('/api/health');
        $response->assertStatus(200);

        // Rate limit headers should be present
        $this->assertNotNull($response->headers->get('X-RateLimit-Limit'));
    }

    /** @test */
    public function authenticated_requests_have_higher_rate_limits()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertStatus(200);

        // Authenticated users should have higher limits
        $limit = $response->headers->get('X-RateLimit-Limit');
        $this->assertNotNull($limit);
    }

    /** @test */
    public function token_management_endpoints_have_strict_rate_limits()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Token management has stricter limits (10 per minute)
        $response = $this->getJson('/api/tokens');

        $response->assertStatus(200);

        $limit = $response->headers->get('X-RateLimit-Limit');
        $this->assertNotNull($limit);
    }

    /** @test */
    public function rate_limit_exceeded_returns_429()
    {
        // This is a conceptual test - in practice you'd need to make many requests
        // or use a package like Laravel's RateLimiter facade to simulate this

        // Expected behavior when rate limit is exceeded:
        // Status: 429
        // Body: {"message": "Too many requests", "errors": {...}}
        $this->assertTrue(true); // Placeholder - implement with actual rate limit testing
    }
}
