# Provider Health Check - Quick Start Guide

## 5-Minute Setup

### 1. Configure Environment Variables

```bash
# .env
ANTHROPIC_API_KEY=your_claude_api_key_here
OLLAMA_BASE_URL=http://127.0.0.1:11434
LMSTUDIO_BASE_URL=http://127.0.0.1:1234/v1
```

### 2. Check Provider Status

**Via API**:
```bash
# Get all provider statuses
curl http://localhost:8000/api/providers/health

# Check specific provider
curl http://localhost:8000/api/providers/health/ollama
```

**Via Dashboard**:
Visit `http://localhost:8000/dashboard` - the health widget displays automatically.

### 3. Use in Your Code

**Basic Dispatch** (will check health automatically):
```php
use App\Services\LLMQueryDispatcher;

$dispatcher = app(LLMQueryDispatcher::class);

$query = $dispatcher->dispatch(
    'ollama',                    // Provider
    'Hello, world!',             // Prompt
    'llama3.2',                  // Model (optional)
    ['user_id' => $user->id]    // Options
);
```

**With Automatic Fallback**:
```php
$query = $dispatcher->dispatchWithFallback(
    'ollama',                    // Primary provider
    'Hello, world!',             // Prompt
    'llama3.2',                  // Model
    ['user_id' => $user->id],   // Options
    ['claude', 'lmstudio']      // Fallback providers
);
```

## Common Use Cases

### Skip Health Check (for testing)
```php
$query = $dispatcher->dispatch('ollama', $prompt, null, [
    'skip_health_check' => true
]);
```

### Allow Unhealthy Provider
```php
$query = $dispatcher->dispatch('ollama', $prompt, null, [
    'allow_unhealthy' => true
]);
```

### Find Healthy Provider
```php
$healthy = $dispatcher->findHealthyProvider(['ollama', 'claude', 'lmstudio']);

if ($healthy) {
    $query = $dispatcher->dispatch($healthy, $prompt);
}
```

### Check Provider Health in Code
```php
use App\Services\ProviderHealthCheck;

$healthCheck = app(ProviderHealthCheck::class);

// Check single provider
$status = $healthCheck->check('ollama');
// Returns: ['status' => 'healthy|degraded|unhealthy', 'message' => '...', ...]

// Check if healthy (boolean)
$isHealthy = $healthCheck->isHealthy('ollama'); // true/false

// Check all providers
$allStatuses = $healthCheck->checkAll();
```

### Clear Health Cache
```php
$healthCheck->clearCache('ollama'); // Single provider
$healthCheck->clearCache();          // All providers
```

## Health Status Types

- **healthy** ✅ - Provider is fully operational
- **degraded** ⚠️ - Provider works but has issues (still allows dispatch with warning)
- **unhealthy** ❌ - Provider is not accessible (blocks dispatch by default)

## API Endpoints Quick Reference

```bash
# Public endpoints (no auth required)
GET  /api/providers/health                  # All providers
GET  /api/providers/health/summary          # Summary stats
GET  /api/providers/health/{provider}       # Specific provider
GET  /api/providers/health?cache=0          # Force fresh check

# Authenticated endpoints (requires token)
POST /api/providers/health/clear-cache      # Clear cache
```

## Troubleshooting Quick Fixes

### Ollama showing unhealthy?
```bash
ollama serve                    # Start Ollama
ollama pull llama3.2           # Pull a model
curl http://127.0.0.1:11434/api/tags  # Test endpoint
```

### LM Studio showing unhealthy?
1. Open LM Studio application
2. Enable "Local Server" in settings
3. Load a model
4. Test: `curl http://127.0.0.1:1234/v1/models`

### Claude API showing unhealthy?
```bash
# Check .env file has valid API key
grep ANTHROPIC_API_KEY .env

# Test key validity
curl https://api.anthropic.com/v1/messages \
  -H "x-api-key: $ANTHROPIC_API_KEY" \
  -H "anthropic-version: 2023-06-01" \
  -H "content-type: application/json" \
  -d '{"model":"claude-3-5-haiku-20241022","max_tokens":10,"messages":[{"role":"user","content":"test"}]}'
```

## Testing

```bash
# Run all health check tests
php artisan test --filter="ProviderHealth"

# Run specific test suite
php artisan test --filter=ProviderHealthCheckTest
php artisan test --filter=ProviderHealthApiTest
php artisan test --filter=LLMQueryDispatcherHealthCheckTest
```

## Performance Tips

1. **Use cached checks** for better performance (default behavior)
2. **Adjust cache TTL** if needed:
   ```php
   $healthCheck->setCacheTtl(120); // 2 minutes
   ```
3. **Use fallback** for critical operations to ensure reliability
4. **Monitor logs** for health-related warnings:
   ```bash
   php artisan pail --timeout=0
   ```

## Integration Examples

### In a Controller
```php
use App\Services\LLMQueryDispatcher;

class ChatController extends Controller
{
    public function send(Request $request, LLMQueryDispatcher $dispatcher)
    {
        try {
            $query = $dispatcher->dispatchWithFallback(
                $request->provider,
                $request->message,
                $request->model,
                ['user_id' => auth()->id()],
                ['claude', 'ollama', 'lmstudio']
            );

            return response()->json(['query_id' => $query->id]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => 'All providers unavailable',
                'message' => $e->getMessage()
            ], 503);
        }
    }
}
```

### In a Job
```php
use App\Services\LLMQueryDispatcher;

class ProcessBatchJob implements ShouldQueue
{
    public function handle(LLMQueryDispatcher $dispatcher)
    {
        // Find any healthy provider
        $provider = $dispatcher->findHealthyProvider();

        if (!$provider) {
            $this->fail('No healthy providers available');
            return;
        }

        $query = $dispatcher->dispatch(
            $provider,
            $this->prompt,
            null,
            ['skip_health_check' => true] // Already checked
        );
    }
}
```

### In a Command
```php
use App\Services\ProviderHealthCheck;

class CheckProvidersCommand extends Command
{
    public function handle(ProviderHealthCheck $healthCheck)
    {
        $statuses = $healthCheck->checkAll();

        foreach ($statuses as $provider => $status) {
            $emoji = match($status['status']) {
                'healthy' => '✅',
                'degraded' => '⚠️',
                'unhealthy' => '❌',
                default => '❓'
            };

            $this->line("{$emoji} {$provider}: {$status['message']}");
        }
    }
}
```

## Next Steps

- Read full documentation: `PROVIDER_HEALTH_CHECKS.md`
- View implementation details: `HEALTH_CHECK_IMPLEMENTATION_SUMMARY.md`
- Check test coverage: `tests/Feature/ProviderHealthCheckTest.php`
- Monitor dashboard: `http://localhost:8000/dashboard`

## Support

If you encounter issues:
1. Check the troubleshooting section above
2. Review logs: `php artisan pail --timeout=0`
3. Test health endpoints: `curl http://localhost:8000/api/providers/health`
4. Clear cache: `curl -X POST http://localhost:8000/api/providers/health/clear-cache`

---

**Quick Links**:
- Service: `/app/Services/ProviderHealthCheck.php`
- Controller: `/app/Http/Controllers/Api/ProviderHealthController.php`
- Widget: `/resources/views/components/provider-health-widget.blade.php`
- Tests: `/tests/Feature/*ProviderHealth*.php`
