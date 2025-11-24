<?php

namespace Tests\Feature;

use App\Models\LLMQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LLMQueryUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_renders(): void
    {
        $this->actingAs(User::factory()->withPersonalTeam()->create());

        $response = $this->get('/llm-queries/create');

        $response->assertStatus(200);
        $response->assertSee('Create New LLM Query');
    }

    public function test_can_dispatch_query_via_form(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $response = $this->post('/llm-queries', [
            'provider' => 'local-command',
            'prompt' => 'Say hello',
            'model' => null,
        ]);

        $response->assertRedirect();

        $query = LLMQuery::first();
        $this->assertNotNull($query);
        $this->assertSame($user->id, $query->user_id);
        $this->assertSame('local-command', $query->provider);
        $this->assertContains($query->status, ['pending', 'processing', 'completed', 'failed']);
    }

    public function test_index_lists_user_queries(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        LLMQuery::factory()->create([
            'user_id' => $user->id,
            'provider' => 'lmstudio',
            'prompt' => 'Hello world',
        ]);

        $response = $this->get('/llm-queries');
        $response->assertStatus(200);
        $response->assertSee('LLM Queries');
        $response->assertSee('lmstudio');
    }

    public function test_show_respects_authorization(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $other = User::factory()->withPersonalTeam()->create();

        $query = LLMQuery::factory()->create([
            'user_id' => $owner->id,
            'provider' => 'lmstudio',
            'prompt' => 'Hello',
        ]);

        // Owner can view
        $this->actingAs($owner);
        $this->get("/llm-queries/{$query->id}")->assertStatus(200);

        // Other user cannot
        $this->actingAs($other);
        $this->get("/llm-queries/{$query->id}")->assertStatus(403);
    }
}
