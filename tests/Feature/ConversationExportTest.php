<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\LLMQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user
        $this->user = User::factory()->create();

        // Create a conversation
        $this->conversation = Conversation::create([
            'user_id' => $this->user->id,
            'title' => 'Test Conversation',
            'provider' => 'claude',
            'model' => 'claude-3-5-sonnet-20241022',
            'last_message_at' => now(),
        ]);

        // Add some messages
        $userMessage = ConversationMessage::create([
            'conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'Hello, how are you?',
        ]);

        $query = LLMQuery::create([
            'user_id' => $this->user->id,
            'conversation_id' => $this->conversation->id,
            'provider' => 'claude',
            'model' => 'claude-3-5-sonnet-20241022',
            'prompt' => 'Hello, how are you?',
            'response' => 'I am doing well, thank you!',
            'status' => 'completed',
            'duration_ms' => 1250,
            'finish_reason' => 'end_turn',
            'usage_stats' => [
                'total_tokens' => 150,
                'prompt_tokens' => 50,
                'completion_tokens' => 100,
            ],
        ]);

        ConversationMessage::create([
            'conversation_id' => $this->conversation->id,
            'role' => 'assistant',
            'content' => 'I am doing well, thank you!',
            'llm_query_id' => $query->id,
        ]);
    }

    public function test_can_export_conversation_as_json(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('conversations.export.json', $this->conversation));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertHeader('Content-Disposition');

        $data = $response->json();

        $this->assertArrayHasKey('exported_at', $data);
        $this->assertArrayHasKey('conversation', $data);
        $this->assertArrayHasKey('messages', $data);
        $this->assertArrayHasKey('queries_summary', $data);

        $this->assertEquals('Test Conversation', $data['conversation']['title']);
        $this->assertEquals('claude', $data['conversation']['provider']);
        $this->assertCount(2, $data['messages']);
        $this->assertEquals('user', $data['messages'][0]['role']);
        $this->assertEquals('assistant', $data['messages'][1]['role']);
        $this->assertNotNull($data['messages'][1]['llm_query']);
        $this->assertEquals(150, $data['messages'][1]['llm_query']['usage_stats']['total_tokens']);
    }

    public function test_can_export_conversation_as_markdown(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('conversations.export.markdown', $this->conversation));

        $response->assertStatus(200);
        $this->assertTrue(
            str_starts_with($response->headers->get('Content-Type'), 'text/markdown'),
            'Content-Type header should be text/markdown'
        );
        $response->assertHeader('Content-Disposition');

        $content = $response->getContent();

        $this->assertStringContainsString('# Test Conversation', $content);
        $this->assertStringContainsString('## Conversation Details', $content);
        $this->assertStringContainsString('## Conversation History', $content);
        $this->assertStringContainsString('Hello, how are you?', $content);
        $this->assertStringContainsString('I am doing well, thank you!', $content);
        $this->assertStringContainsString('**Provider:** claude', $content);
        $this->assertStringContainsString('**Model:** claude-3-5-sonnet-20241022', $content);
        $this->assertStringContainsString('**Tokens:** 150', $content);
        $this->assertStringContainsString('**Duration:** 1.25s', $content);
    }

    public function test_cannot_export_other_users_conversation(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->get(route('conversations.export.json', $this->conversation));

        $response->assertStatus(403);

        $response = $this->actingAs($otherUser)
            ->get(route('conversations.export.markdown', $this->conversation));

        $response->assertStatus(403);
    }

    public function test_guests_cannot_export_conversations(): void
    {
        $response = $this->get(route('conversations.export.json', $this->conversation));
        $response->assertRedirect(route('login'));

        $response = $this->get(route('conversations.export.markdown', $this->conversation));
        $response->assertRedirect(route('login'));
    }

    public function test_json_export_includes_complete_metadata(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('conversations.export.json', $this->conversation));

        $data = $response->json();

        // Check conversation metadata
        $this->assertArrayHasKey('id', $data['conversation']);
        $this->assertArrayHasKey('title', $data['conversation']);
        $this->assertArrayHasKey('provider', $data['conversation']);
        $this->assertArrayHasKey('model', $data['conversation']);
        $this->assertArrayHasKey('created_at', $data['conversation']);
        $this->assertArrayHasKey('last_message_at', $data['conversation']);

        // Check user metadata
        $this->assertArrayHasKey('name', $data['user']);
        $this->assertArrayHasKey('email', $data['user']);

        // Check message structure
        foreach ($data['messages'] as $message) {
            $this->assertArrayHasKey('id', $message);
            $this->assertArrayHasKey('role', $message);
            $this->assertArrayHasKey('content', $message);
            $this->assertArrayHasKey('created_at', $message);
        }

        // Check queries summary
        $this->assertArrayHasKey('total', $data['queries_summary']);
        $this->assertArrayHasKey('by_status', $data['queries_summary']);
    }

    public function test_markdown_export_formats_correctly(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('conversations.export.markdown', $this->conversation));

        $content = $response->getContent();

        // Check for proper Markdown formatting
        $this->assertMatchesRegularExpression('/^# Test Conversation\n\n/', $content);
        $this->assertMatchesRegularExpression('/## Conversation Details\n\n/', $content);
        $this->assertMatchesRegularExpression('/## Conversation History\n\n/', $content);
        $this->assertMatchesRegularExpression('/### Message \d+: .+ (User|Assistant)\n\n/', $content);
        $this->assertMatchesRegularExpression('/#### Query Metadata\n\n/', $content);
        $this->assertMatchesRegularExpression('/## Export Information\n\n/', $content);

        // Check for proper metadata formatting
        $this->assertStringContainsString('- **Created:**', $content);
        $this->assertStringContainsString('- **Provider:**', $content);
        $this->assertStringContainsString('- **Model:**', $content);
        $this->assertStringContainsString('- **Total Messages:** 2', $content);
    }
}
