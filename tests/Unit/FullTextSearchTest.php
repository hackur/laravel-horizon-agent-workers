<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FullTextSearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_full_text_search_finds_conversations_by_message_content(): void
    {
        // Create a conversation with specific message content
        $conversation = Conversation::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Regular conversation',
        ]);

        ConversationMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'content' => 'This message contains the word Laravel framework',
            'role' => 'user',
        ]);

        // Create another conversation without the search term
        $otherConversation = Conversation::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Another conversation',
        ]);

        ConversationMessage::factory()->create([
            'conversation_id' => $otherConversation->id,
            'content' => 'This is about something else',
            'role' => 'user',
        ]);

        // Search for "Laravel"
        $results = Conversation::where('user_id', $this->user->id)
            ->fullTextSearch('Laravel')
            ->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->contains($conversation));
        $this->assertFalse($results->contains($otherConversation));
    }

    public function test_full_text_search_finds_conversations_by_title(): void
    {
        // Create a conversation with specific title
        $conversation = Conversation::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Discussion about Laravel',
        ]);

        // Create another conversation without the search term
        $otherConversation = Conversation::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'React discussion',
        ]);

        // Search for "Laravel"
        $results = Conversation::where('user_id', $this->user->id)
            ->fullTextSearch('Laravel')
            ->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->contains($conversation));
        $this->assertFalse($results->contains($otherConversation));
    }

    public function test_full_text_search_handles_phrase_search(): void
    {
        $conversation = Conversation::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Test conversation',
        ]);

        ConversationMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'content' => 'The Laravel framework is awesome',
            'role' => 'user',
        ]);

        // Search for exact phrase
        $results = Conversation::where('user_id', $this->user->id)
            ->fullTextSearch('Laravel framework')
            ->get();

        $this->assertCount(1, $results);
    }

    public function test_search_excerpt_returns_context_around_match(): void
    {
        $message = ConversationMessage::factory()->create([
            'content' => 'This is a long message about Laravel framework and how it helps developers build applications faster.',
            'role' => 'user',
        ]);

        $excerpt = $message->getSearchExcerpt('Laravel', 50);

        $this->assertStringContainsString('Laravel', $excerpt);
        $this->assertLessThanOrEqual(56, strlen($excerpt)); // 50 + '...' characters (allowing for position)
    }

    public function test_highlight_search_term_adds_mark_tags(): void
    {
        $message = ConversationMessage::factory()->create([
            'content' => 'This is about Laravel',
            'role' => 'user',
        ]);

        $highlighted = $message->highlightSearchTerm('This is about Laravel', 'Laravel');

        $this->assertStringContainsString('<mark', $highlighted);
        $this->assertStringContainsString('Laravel', $highlighted);
    }

    public function test_highlight_is_case_insensitive(): void
    {
        $message = ConversationMessage::factory()->create([
            'content' => 'Laravel Framework',
            'role' => 'user',
        ]);

        $highlighted = $message->highlightSearchTerm('Laravel Framework', 'laravel');

        $this->assertStringContainsString('<mark', $highlighted);
    }

    public function test_date_range_scope_filters_correctly(): void
    {
        // Create conversations with different dates
        $oldConversation = Conversation::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now()->subDays(10),
        ]);

        $recentConversation = Conversation::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now()->subDays(2),
        ]);

        // Filter by date range (last 5 days)
        $results = Conversation::where('user_id', $this->user->id)
            ->dateRange(now()->subDays(5)->format('Y-m-d'), now()->format('Y-m-d'))
            ->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->contains($recentConversation));
        $this->assertFalse($results->contains($oldConversation));
    }

    public function test_provider_filter_works(): void
    {
        $claudeConversation = Conversation::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'claude',
        ]);

        $ollamaConversation = Conversation::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'ollama',
        ]);

        $results = Conversation::where('user_id', $this->user->id)
            ->byProvider('claude')
            ->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->contains($claudeConversation));
        $this->assertFalse($results->contains($ollamaConversation));
    }

    public function test_empty_search_returns_all_conversations(): void
    {
        $conversation1 = Conversation::factory()->create(['user_id' => $this->user->id]);
        $conversation2 = Conversation::factory()->create(['user_id' => $this->user->id]);

        $results = Conversation::where('user_id', $this->user->id)
            ->fullTextSearch('')
            ->get();

        $this->assertCount(2, $results);
    }

    public function test_search_is_user_specific(): void
    {
        $otherUser = User::factory()->create();

        // Create conversation for this user
        $myConversation = Conversation::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'My Laravel conversation',
        ]);

        // Create conversation for other user
        $theirConversation = Conversation::factory()->create([
            'user_id' => $otherUser->id,
            'title' => 'Their Laravel conversation',
        ]);

        // Search as current user
        $results = Conversation::where('user_id', $this->user->id)
            ->fullTextSearch('Laravel')
            ->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->contains($myConversation));
        $this->assertFalse($results->contains($theirConversation));
    }
}
