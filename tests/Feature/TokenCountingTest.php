<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Services\ConversationService;
use App\Services\TokenCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenCountingTest extends TestCase
{
    use RefreshDatabase;

    protected TokenCounter $tokenCounter;

    protected ConversationService $conversationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenCounter = app(TokenCounter::class);
        $this->conversationService = app(ConversationService::class);
    }

    public function test_token_counter_counts_simple_text(): void
    {
        $text = 'Hello, world!';
        $tokens = $this->tokenCounter->count($text);

        $this->assertGreaterThan(0, $tokens);
        $this->assertLessThan(20, $tokens); // Should be around 3-4 tokens
    }

    public function test_token_counter_handles_empty_text(): void
    {
        $tokens = $this->tokenCounter->count('');

        $this->assertEquals(0, $tokens);
    }

    public function test_token_counter_counts_message(): void
    {
        $message = [
            'role' => 'user',
            'content' => 'This is a test message with some content.',
        ];

        $tokens = $this->tokenCounter->countMessage($message);

        $this->assertGreaterThan(0, $tokens);
    }

    public function test_token_counter_counts_multiple_messages(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Hello!'],
            ['role' => 'assistant', 'content' => 'Hi there! How can I help you?'],
            ['role' => 'user', 'content' => 'I need help with my code.'],
        ];

        $tokens = $this->tokenCounter->countMessages($messages);

        $this->assertGreaterThan(0, $tokens);
    }

    public function test_get_context_limit_for_claude(): void
    {
        $limit = $this->tokenCounter->getContextLimit('claude-3-5-sonnet-20241022');

        $this->assertEquals(200000, $limit);
    }

    public function test_get_safe_context_limit_reserves_buffer(): void
    {
        $fullLimit = $this->tokenCounter->getContextLimit('claude-3-5-sonnet-20241022');
        $safeLimit = $this->tokenCounter->getSafeContextLimit('claude-3-5-sonnet-20241022');

        $this->assertLessThan($fullLimit, $safeLimit);
        $this->assertEquals(196000, $safeLimit); // 200k - 4k buffer for Claude
    }

    public function test_warning_level_safe(): void
    {
        $safeLimit = $this->tokenCounter->getSafeContextLimit('claude');
        $currentTokens = (int) ($safeLimit * 0.5); // 50% usage

        $warningLevel = $this->tokenCounter->getWarningLevel($currentTokens, 'claude');

        $this->assertEquals('safe', $warningLevel);
    }

    public function test_warning_level_warning(): void
    {
        $safeLimit = $this->tokenCounter->getSafeContextLimit('claude');
        $currentTokens = (int) ($safeLimit * 0.8); // 80% usage

        $warningLevel = $this->tokenCounter->getWarningLevel($currentTokens, 'claude');

        $this->assertEquals('warning', $warningLevel);
    }

    public function test_warning_level_critical(): void
    {
        $safeLimit = $this->tokenCounter->getSafeContextLimit('claude');
        $currentTokens = (int) ($safeLimit * 0.95); // 95% usage

        $warningLevel = $this->tokenCounter->getWarningLevel($currentTokens, 'claude');

        $this->assertEquals('critical', $warningLevel);
    }

    public function test_warning_level_exceeded(): void
    {
        $safeLimit = $this->tokenCounter->getSafeContextLimit('claude');
        $currentTokens = (int) ($safeLimit * 1.1); // 110% usage

        $warningLevel = $this->tokenCounter->getWarningLevel($currentTokens, 'claude');

        $this->assertEquals('exceeded', $warningLevel);
    }

    public function test_conversation_context_returns_messages(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'provider' => 'claude',
            'model' => 'claude-3-5-sonnet-20241022',
        ]);

        // Add some messages
        ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Hello!',
        ]);

        ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Hi there!',
        ]);

        $context = $this->conversationService->getConversationContext($conversation);

        $this->assertIsArray($context);
        $this->assertCount(2, $context);
        $this->assertEquals('user', $context[0]['role']);
        $this->assertEquals('assistant', $context[1]['role']);
    }

    public function test_conversation_context_with_token_info(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'provider' => 'claude',
            'model' => 'claude-3-5-sonnet-20241022',
        ]);

        // Add a message
        ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'This is a test message.',
        ]);

        $contextData = $this->conversationService->getConversationContextWithTokenInfo($conversation);

        $this->assertIsArray($contextData);
        $this->assertArrayHasKey('messages', $contextData);
        $this->assertArrayHasKey('token_info', $contextData);

        $tokenInfo = $contextData['token_info'];
        $this->assertArrayHasKey('current_tokens', $tokenInfo);
        $this->assertArrayHasKey('safe_limit', $tokenInfo);
        $this->assertArrayHasKey('usage_percent', $tokenInfo);
        $this->assertArrayHasKey('warning_level', $tokenInfo);
        $this->assertArrayHasKey('was_truncated', $tokenInfo);
        $this->assertArrayHasKey('messages_count', $tokenInfo);

        $this->assertGreaterThan(0, $tokenInfo['current_tokens']);
        $this->assertEquals(196000, $tokenInfo['safe_limit']);
        $this->assertFalse($tokenInfo['was_truncated']);
    }

    public function test_conversation_context_truncates_when_over_limit(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'provider' => 'ollama', // Smaller limit for testing
            'model' => 'llama2',
        ]);

        // Create a very long message that will exceed the limit
        $longContent = str_repeat('This is a very long message. ', 500); // ~2500 words

        // Add multiple long messages
        for ($i = 0; $i < 10; $i++) {
            ConversationMessage::create([
                'conversation_id' => $conversation->id,
                'role' => $i % 2 === 0 ? 'user' : 'assistant',
                'content' => $longContent,
            ]);
        }

        $contextData = $this->conversationService->getConversationContextWithTokenInfo($conversation);

        $tokenInfo = $contextData['token_info'];

        // Should have truncated some messages since we added so many long ones
        $this->assertTrue($tokenInfo['was_truncated'], 'Expected context to be truncated');
        $this->assertGreaterThan(0, $tokenInfo['messages_removed'], 'Expected some messages to be removed');
        $this->assertLessThan($tokenInfo['original_messages_count'], $tokenInfo['messages_count'], 'Expected fewer messages in final context');

        // The final token count might exceed safe limit by a bit due to minimum message requirement
        // but should be less than original
        $this->assertLessThan($tokenInfo['original_tokens'], $tokenInfo['current_tokens'], 'Expected token count to be reduced');
    }

    public function test_format_token_count(): void
    {
        $this->assertEquals('500', $this->tokenCounter->formatTokenCount(500, false));
        $this->assertEquals('1.5K', $this->tokenCounter->formatTokenCount(1500));
        $this->assertEquals('150.0K', $this->tokenCounter->formatTokenCount(150000));
        $this->assertEquals('1.2M', $this->tokenCounter->formatTokenCount(1200000));
    }

    public function test_model_display_name(): void
    {
        $this->assertEquals('Claude 3.5 Sonnet', $this->tokenCounter->getModelDisplayName('claude-3-5-sonnet-20241022'));
        $this->assertEquals('Claude 3 Opus', $this->tokenCounter->getModelDisplayName('claude-3-opus-20240229'));
        $this->assertEquals('GPT-4', $this->tokenCounter->getModelDisplayName('gpt-4'));
    }
}
