<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function unauthenticated_requests_return_401()
    {
        $response = $this->getJson('/api/conversations');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated. Please provide a valid API token.',
            ]);
    }

    /** @test */
    public function authenticated_requests_with_valid_token_succeed()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJson([
                'id' => $user->id,
                'email' => $user->email,
            ]);
    }

    /** @test */
    public function user_can_create_api_token()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/tokens', [
            'name' => 'Test Token',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'token',
                'tokenObject' => [
                    'id',
                    'name',
                    'abilities',
                    'created_at',
                ],
            ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'Test Token',
        ]);
    }

    /** @test */
    public function user_can_list_their_tokens()
    {
        $user = User::factory()->create();
        $token1 = $user->createToken('Token 1');
        $token2 = $user->createToken('Token 2');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/tokens');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'Token 1'])
            ->assertJsonFragment(['name' => 'Token 2']);
    }

    /** @test */
    public function user_can_delete_their_token()
    {
        $user = User::factory()->create();
        $token = $user->createToken('Test Token');

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/tokens/{$token->accessToken->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Token deleted successfully',
            ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    /** @test */
    public function user_cannot_delete_another_users_token()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $token = $user2->createToken('User 2 Token');

        Sanctum::actingAs($user1);

        $response = $this->deleteJson("/api/tokens/{$token->accessToken->id}");

        $response->assertStatus(404);

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    /** @test */
    public function health_endpoint_works_without_authentication()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
            ]);
    }

    /** @test */
    public function api_responses_include_security_headers()
    {
        $response = $this->getJson('/api/health');

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-XSS-Protection', '1; mode=block');
    }
}
