# Token Counting Implementation

This document describes the token counting implementation for managing conversation context windows and preventing model limit overruns.

## Overview

The token counting system provides:

1. **Accurate token estimation** for different LLM models
2. **Automatic context truncation** when conversations exceed model limits
3. **Visual indicators** in the UI showing token usage
4. **Warning levels** (safe, warning, critical, exceeded)
5. **Logging** when context is truncated or approaching limits

## Components

### 1. TokenCounter Service (`app/Services/TokenCounter.php`)

The core service that handles all token counting operations.

#### Key Features:

- **Model-specific token limits**: Supports Claude (200k), GPT-4 (128k), Ollama (8k), etc.
- **Accurate token estimation**: Uses ~3.5 chars/token for Claude, ~4.0 for others
- **Safe limits with buffers**: Reserves tokens for model responses (4k for Claude, 2k for others)
- **Warning levels**: Returns 'safe', 'warning', 'critical', or 'exceeded' based on usage
- **Display formatting**: Formats token counts (e.g., "1.5K", "150.0K")

#### Main Methods:

```php
// Count tokens in a string
$tokens = $tokenCounter->count($text, $model);

// Count tokens in a message array
$tokens = $tokenCounter->countMessage(['role' => 'user', 'content' => '...'], $model);

// Count tokens in multiple messages
$tokens = $tokenCounter->countMessages($messages, $model);

// Get context limit
$limit = $tokenCounter->getContextLimit($model); // Full limit
$safeLimit = $tokenCounter->getSafeContextLimit($model); // With buffer

// Get usage information
$percent = $tokenCounter->getContextUsagePercent($currentTokens, $model);
$remaining = $tokenCounter->getRemainingTokens($currentTokens, $model);
$level = $tokenCounter->getWarningLevel($currentTokens, $model);
$isApproaching = $tokenCounter->isApproachingLimit($currentTokens, $model, 80.0);
```

### 2. ConversationService Enhancements (`app/Services/ConversationService.php`)

Enhanced to integrate token counting and automatic truncation.

#### New/Modified Methods:

```php
// Get conversation context with automatic truncation
$messages = $conversationService->getConversationContext($conversation);

// Get conversation context WITH token information
$contextData = $conversationService->getConversationContextWithTokenInfo($conversation);
// Returns:
// [
//   'messages' => [...],
//   'token_info' => [
//     'current_tokens' => 1500,
//     'safe_limit' => 196000,
//     'usage_percent' => 0.77,
//     'warning_level' => 'safe',
//     'was_truncated' => false,
//     'messages_count' => 10,
//     'messages_removed' => 0,
//     ...
//   ]
// ]
```

#### Truncation Behavior:

- When context exceeds the safe limit, older messages are removed from the beginning
- Always preserves at least 2 messages (one exchange) for context coherence
- Logs warnings when truncation occurs
- Logs info messages when approaching limits (>75%)

### 3. UI Integration (`resources/views/conversations/show.blade.php`)

Added a comprehensive token usage panel that displays:

- **Token usage bar**: Visual progress bar showing usage percentage
- **Color-coded warnings**: Green (safe), yellow (warning), red (critical/exceeded)
- **Detailed statistics**: Current tokens, remaining, model info, message count
- **Truncation notices**: Alerts when messages have been removed
- **Warning messages**: Context-specific alerts based on usage level

#### Warning Levels:

- **Safe (green)**: < 75% usage - Normal operation
- **Warning (yellow)**: 75-89% usage - "Consider starting a new conversation soon"
- **Critical (red)**: 90-99% usage - "Context nearly full. Future messages may be truncated"
- **Exceeded (red)**: ≥100% usage - "Context limit exceeded! Older messages have been truncated"

### 4. ConversationController Updates (`app/Http/Controllers/ConversationController.php`)

The `show()` method now passes token information to the view:

```php
public function show(Conversation $conversation)
{
    // ... existing code ...

    // Get token information
    $contextData = $this->conversationService->getConversationContextWithTokenInfo($conversation);

    return view('conversations.show', [
        'conversation' => $conversation,
        'providers' => $this->dispatcher->getProviders(),
        'statistics' => $statistics,
        'tokenInfo' => $contextData['token_info'],
    ]);
}
```

## Model Context Limits

| Model/Provider | Full Limit | Safe Limit | Buffer |
|---------------|------------|------------|--------|
| Claude 3.5 (all) | 200,000 | 196,000 | 4,000 |
| Claude 3 (all) | 200,000 | 196,000 | 4,000 |
| GPT-4 | 128,000 | 126,000 | 2,000 |
| GPT-3.5 Turbo | 16,385 | 14,385 | 2,000 |
| Ollama | 8,192 | 6,192 | 2,000 |
| LM Studio | 4,096 | 2,096 | 2,000 |
| Default | 4,096 | 2,096 | 2,000 |

## Token Estimation Accuracy

The service uses character-based estimation:

- **Claude models**: ~3.5 characters per token
- **GPT models**: ~4.0 characters per token
- **Other models**: ~4.0 characters per token (conservative)

Additional overhead is added for:
- Message formatting (role indicators, delimiters): 4 tokens per message
- Overall structure: 2% overhead with 4 token minimum

## Logging

Token-related events are logged automatically:

### Warning Logs (when truncation occurs):

```log
Conversation context truncated due to token limit
{
    "conversation_id": 123,
    "model": "claude-3-5-sonnet-20241022",
    "original_messages": 50,
    "truncated_messages": 30,
    "messages_removed": 20,
    "original_tokens": 210000,
    "safe_limit": 196000,
    "final_tokens": 195500
}
```

### Info Logs (when approaching limit):

```log
Conversation context approaching token limit
{
    "conversation_id": 123,
    "model": "claude-3-5-sonnet-20241022",
    "total_tokens": 150000,
    "safe_limit": 196000,
    "usage_percent": 76.53,
    "message_count": 40
}
```

## Testing

Comprehensive test suite in `tests/Feature/TokenCountingTest.php` covers:

- Basic token counting functionality
- Message and multi-message counting
- Context limit retrieval
- Warning level calculation
- Conversation context retrieval
- Automatic truncation
- Token information with context
- Display formatting
- Model name display

Run tests:

```bash
php artisan test tests/Feature/TokenCountingTest.php
```

## Usage Examples

### Example 1: Basic Token Counting

```php
$tokenCounter = app(TokenCounter::class);

$text = "Hello, how can I help you today?";
$tokens = $tokenCounter->count($text, 'claude-3-5-sonnet-20241022');
// Returns: ~10 tokens

$formatted = $tokenCounter->formatTokenCount($tokens);
// Returns: "10"

$largeNumber = $tokenCounter->formatTokenCount(150000);
// Returns: "150.0K"
```

### Example 2: Check Context Usage

```php
$tokenCounter = app(TokenCounter::class);
$model = 'claude-3-5-sonnet-20241022';

$currentTokens = 150000;

$percent = $tokenCounter->getContextUsagePercent($currentTokens, $model);
// Returns: 76.53

$remaining = $tokenCounter->getRemainingTokens($currentTokens, $model);
// Returns: 46000

$level = $tokenCounter->getWarningLevel($currentTokens, $model);
// Returns: "warning"

if ($tokenCounter->isApproachingLimit($currentTokens, $model, 75.0)) {
    // Warn user about approaching limit
}
```

### Example 3: Get Conversation Context with Token Info

```php
$conversationService = app(ConversationService::class);
$conversation = Conversation::find($id);

$contextData = $conversationService->getConversationContextWithTokenInfo($conversation);

if ($contextData['token_info']['was_truncated']) {
    Log::warning('Messages were truncated', [
        'removed' => $contextData['token_info']['messages_removed'],
        'keeping' => $contextData['token_info']['messages_count'],
    ]);
}

// Use the messages for LLM query
$messages = $contextData['messages'];
```

## Best Practices

1. **Always use the safe limit**: Don't rely on the full context window - always use `getSafeContextLimit()` to leave room for the model's response.

2. **Monitor warning levels**: Pay attention to warning levels and inform users when they're approaching limits.

3. **Log truncation events**: The system automatically logs when truncation occurs, but you may want additional application-specific logging.

4. **Consider conversation splitting**: When conversations get very long, consider suggesting users start a new conversation rather than relying solely on truncation.

5. **Test with different models**: Different models have different limits - test your implementation with various providers.

6. **Display token usage**: Show users their token usage so they understand why truncation might occur.

## Future Enhancements

Potential improvements for the future:

1. **Smart truncation**: Instead of removing oldest messages, implement intelligent selection (keep important context, remove filler)
2. **Conversation summarization**: Automatically summarize old messages instead of removing them
3. **Token-based pagination**: Load messages on-demand based on token budget
4. **User preferences**: Allow users to set their own truncation preferences
5. **Real token counting**: Integrate with actual tokenizer libraries for precise counts (tiktoken, etc.)
6. **Budget alerts**: Send notifications when users approach their token budgets
7. **Per-user limits**: Set different limits for different user tiers

## Migration Notes

If you're upgrading an existing application:

1. The `ConversationService` now requires `TokenCounter` in its constructor (handled by dependency injection)
2. Token information is automatically calculated for conversation views
3. No database changes are required
4. Existing conversations will work seamlessly
5. The system is backward compatible - it won't break existing functionality

## Conclusion

This token counting implementation provides a robust foundation for managing conversation context windows across different LLM providers. It prevents exceeding model limits while providing transparency to users about their token usage.
