<?php

namespace Tests\Feature\Api;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_list_their_conversations()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Conversation::factory()->count(3)->create(['user_id' => $user->id]);
        Conversation::factory()->count(2)->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/conversations');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'provider',
                        'model',
                        'user_id',
                        'created_at',
                        'links',
                    ],
                ],
                'meta' => [
                    'total',
                    'count',
                    'per_page',
                    'current_page',
                    'total_pages',
                ],
            ]);
    }

    /** @test */
    public function user_can_filter_conversations_by_provider()
    {
        $user = User::factory()->create();

        Conversation::factory()->create([
            'user_id' => $user->id,
            'provider' => 'claude',
        ]);
        Conversation::factory()->create([
            'user_id' => $user->id,
            'provider' => 'ollama',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/conversations?provider=claude');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.provider', 'claude');
    }

    /** @test */
    public function user_can_view_their_conversation()
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/conversations/{$conversation->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                ],
            ]);
    }

    /** @test */
    public function user_cannot_view_another_users_conversation()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/conversations/{$conversation->id}");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Unauthorized access to conversation',
            ]);
    }

    /** @test */
    public function user_can_update_their_conversation()
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'title' => 'Old Title',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/conversations/{$conversation->id}", [
            'title' => 'New Title',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Conversation updated successfully',
                'data' => [
                    'title' => 'New Title',
                ],
            ]);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'title' => 'New Title',
        ]);
    }

    /** @test */
    public function user_cannot_update_another_users_conversation()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/conversations/{$conversation->id}", [
            'title' => 'Hacked Title',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function user_can_delete_their_conversation()
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/conversations/{$conversation->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseMissing('conversations', [
            'id' => $conversation->id,
        ]);
    }

    /** @test */
    public function validation_errors_return_422()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/conversations', [
            'title' => '', // Invalid: required
            'provider' => 'invalid', // Invalid: not in allowed list
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'title',
                    'provider',
                ],
            ]);
    }

    /** @test */
    public function api_returns_consistent_json_structure_for_errors()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Test validation error
        $response = $this->postJson('/api/conversations', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors',
            ]);

        // Test not found error
        $response = $this->getJson('/api/conversations/99999');

        $response->assertStatus(404)
            ->assertJsonStructure([
                'message',
                'errors',
            ]);
    }
}
