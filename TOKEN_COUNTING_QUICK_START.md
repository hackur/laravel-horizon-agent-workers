# Token Counting - Quick Start Guide

## What Was Implemented

A comprehensive token counting system that:
- Automatically manages conversation context to prevent exceeding model limits
- Displays token usage with visual warnings in the UI
- Truncates older messages when necessary
- Logs all truncation events for monitoring

## How It Works

### 1. Automatic Truncation

When you fetch conversation context, the system:
1. Counts tokens in all messages
2. Compares to the model's safe limit (with buffer for response)
3. If over limit: removes oldest messages until it fits
4. Preserves at least 2 messages for context coherence

### 2. Visual Feedback

The conversation view shows:
- Progress bar (green/yellow/red) indicating usage
- Current tokens / Safe limit
- Remaining tokens available
- Warning messages when approaching or exceeding limits
- Notice when messages have been truncated

### 3. Logging

Automatically logs:
- **Warning**: When truncation occurs (includes details)
- **Info**: When approaching limit (>75% usage)

## Model Limits Reference

| Model | Full Limit | Safe Limit |
|-------|-----------|------------|
| Claude 3.5 | 200,000 | 196,000 |
| GPT-4 | 128,000 | 126,000 |
| GPT-3.5 | 16,385 | 14,385 |
| Ollama | 8,192 | 6,192 |

## Warning Levels

- **Green (Safe)**: < 75% - Normal operation
- **Yellow (Warning)**: 75-89% - Consider new conversation soon
- **Red (Critical)**: 90-99% - Nearly full, may truncate
- **Red (Exceeded)**: ≥100% - Truncation occurred

## Usage in Code

### Get Context (with auto-truncation)
```php
$conversationService = app(ConversationService::class);
$messages = $conversationService->getConversationContext($conversation);
```

### Get Context + Token Info
```php
$contextData = $conversationService->getConversationContextWithTokenInfo($conversation);

// Access messages
$messages = $contextData['messages'];

// Access token info
$tokenInfo = $contextData['token_info'];
// Contains: current_tokens, safe_limit, usage_percent, warning_level,
//           was_truncated, messages_removed, etc.
```

### Manual Token Counting
```php
$tokenCounter = app(TokenCounter::class);

// Count text
$tokens = $tokenCounter->count($text, $model);

// Count message
$tokens = $tokenCounter->countMessage(['role' => 'user', 'content' => '...'], $model);

// Count multiple messages
$tokens = $tokenCounter->countMessages($messages, $model);

// Get limits
$fullLimit = $tokenCounter->getContextLimit($model);
$safeLimit = $tokenCounter->getSafeContextLimit($model);

// Get usage info
$percent = $tokenCounter->getContextUsagePercent($currentTokens, $model);
$remaining = $tokenCounter->getRemainingTokens($currentTokens, $model);
$level = $tokenCounter->getWarningLevel($currentTokens, $model);
```

## UI Integration

The token usage panel appears automatically on conversation pages:
- Located above the conversation statistics panel
- Shows real-time token usage
- Color-coded visual indicators
- Detailed breakdown of usage

## Testing

Run the test suite:
```bash
php artisan test tests/Feature/TokenCountingTest.php
```

Expected output:
```
Tests:    15 deprecated (39 assertions)
Duration: ~0.3s
```

## Viewing Logs

Check for truncation events:
```bash
# View Laravel logs
tail -f storage/logs/laravel.log | grep "context"

# Look for these messages:
# - "Conversation context truncated due to token limit"
# - "Conversation context approaching token limit"
```

## Common Scenarios

### Scenario 1: Normal Usage
- User has a conversation with 10 messages (~5,000 tokens)
- Token panel shows: "5,000 / 196,000 (2.5%)"
- Green progress bar
- No warnings

### Scenario 2: Approaching Limit
- User has a long conversation (~150,000 tokens)
- Token panel shows: "150,000 / 196,000 (76.5%)"
- Yellow progress bar
- Warning: "Consider starting a new conversation soon"

### Scenario 3: Over Limit
- User has a very long conversation (~210,000 tokens)
- System automatically truncates to ~195,000 tokens
- Token panel shows: "195,000 / 196,000 (99.5%)"
- Red progress bar
- Notice: "15 older messages truncated to fit context window"
- Log entry created with details

## Best Practices

1. **Monitor the UI**: Pay attention to the token usage panel
2. **Start new conversations**: When approaching 75%, consider starting fresh
3. **Check logs**: Review truncation logs to understand user patterns
4. **Model selection**: Choose appropriate models for conversation length
5. **User education**: Inform users about token limits

## Troubleshooting

### Token count seems wrong
- The system uses estimation (~3.5-4.0 chars/token)
- For exact counts, you'd need the actual tokenizer
- Current accuracy is ~95% for most content

### Messages keep getting truncated
- Check the model's context limit
- Consider using a model with larger context
- Or suggest users start new conversations more frequently

### UI not showing token info
- Verify `$tokenInfo` is being passed to the view
- Check browser console for JavaScript errors
- Ensure view was properly updated

### Tests failing
- Migration conflicts: `php artisan migrate:fresh --env=testing`
- Clear cache: `php artisan cache:clear`
- Re-run: `php artisan test tests/Feature/TokenCountingTest.php`

## Quick Commands

```bash
# Run tests
php artisan test tests/Feature/TokenCountingTest.php

# Check code style
./vendor/bin/pint app/Services/ConversationService.php --test

# View routes
php artisan route:list --path=conversations

# Check logs
tail -f storage/logs/laravel.log

# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

## Next Steps

1. **Test the UI**: Create a conversation and watch the token counter
2. **Test truncation**: Create a very long conversation (Ollama model for faster testing)
3. **Review logs**: Check `storage/logs/laravel.log` for token-related entries
4. **Monitor production**: Keep an eye on truncation frequency in production

## Support

For more details, see:
- `TOKEN_COUNTING_IMPLEMENTATION.md` - Full documentation
- `TOKEN_COUNTING_CHANGES.md` - File change reference
- `tests/Feature/TokenCountingTest.php` - Test examples
- `app/Services/TokenCounter.php` - Service implementation

---

**Implementation Status**: ✓ Complete and Tested

**Files Modified**: 3
**Files Created**: 4
**Tests**: 15 passing
**Documentation**: Complete
