# Provider Health Check System

This document describes the provider health check system implemented in the Laravel Horizon Agent Workers application.

## Overview

The provider health check system monitors the availability and health of all LLM service providers (Claude API, Ollama, LM Studio, and Local Command execution). It provides:

- Real-time health status monitoring
- Automatic health checks with caching
- Graceful degradation when providers are unavailable
- Automatic fallback to healthy providers
- Dashboard widget showing provider status
- RESTful API endpoints for health monitoring

## Components

### 1. ProviderHealthCheck Service

**Location**: `/Volumes/JS-DEV/laravel-horizon-agent-workers/app/Services/ProviderHealthCheck.php`

The core service that performs health checks for each provider.

#### Methods

```php
// Check all providers
$statuses = $healthCheck->checkAll();

// Check specific provider
$status = $healthCheck->check('ollama');

// Check with caching (default 60 seconds)
$status = $healthCheck->checkCached('claude');

// Check if provider is healthy
$isHealthy = $healthCheck->isHealthy('lmstudio');

// Clear cache
$healthCheck->clearCache('ollama'); // Specific provider
$healthCheck->clearCache(); // All providers
```

#### Health Status Types

- **healthy**: Provider is fully operational
- **degraded**: Provider is working but with issues (e.g., no models loaded)
- **unhealthy**: Provider is not accessible or has critical errors
- **unknown**: Health check could not determine status

#### Configuration

```php
// Customize cache TTL (default: 60 seconds)
$healthCheck->setCacheTtl(120);

// Customize timeout (default: 5 seconds)
$healthCheck->setTimeout(10);
```

### 2. API Endpoints

**Location**: `/Volumes/JS-DEV/laravel-horizon-agent-workers/routes/api.php`

#### Public Endpoints (no authentication required)

```bash
# Get health status for all providers
GET /api/providers/health?cache=1

# Get health status for specific provider
GET /api/providers/health/{provider}?cache=1

# Get summary of provider statuses
GET /api/providers/health/summary
```

#### Authenticated Endpoints (requires Sanctum token)

```bash
# Clear health check cache
POST /api/providers/health/clear-cache
Content-Type: application/json
{
  "provider": "ollama"  // Optional: omit to clear all
}
```

#### Response Examples

**All Providers**:
```json
{
  "overall_status": "degraded",
  "providers": {
    "claude": {
      "status": "healthy",
      "message": "Claude API is accessible",
      "timestamp": "2025-11-23T10:30:00Z",
      "details": {
        "models_available": true
      }
    },
    "ollama": {
      "status": "degraded",
      "message": "Ollama running but no models found",
      "timestamp": "2025-11-23T10:30:00Z",
      "details": {
        "url": "http://127.0.0.1:11434",
        "message": "Run 'ollama pull llama3.2' to install a model"
      }
    }
  },
  "cached": true,
  "cache_ttl": 60,
  "timestamp": "2025-11-23T10:30:00Z"
}
```

**Summary**:
```json
{
  "summary": {
    "healthy": ["claude"],
    "degraded": ["ollama"],
    "unhealthy": ["lmstudio", "local-command"]
  },
  "counts": {
    "healthy": 1,
    "degraded": 1,
    "unhealthy": 2,
    "total": 4
  },
  "overall_status": "degraded",
  "timestamp": "2025-11-23T10:30:00Z"
}
```

### 3. LLMQueryDispatcher Integration

**Location**: `/Volumes/JS-DEV/laravel-horizon-agent-workers/app/Services/LLMQueryDispatcher.php`

The dispatcher automatically checks provider health before dispatching jobs.

#### Basic Usage

```php
$dispatcher = app(LLMQueryDispatcher::class);

// Normal dispatch (will fail if provider unhealthy)
$query = $dispatcher->dispatch('ollama', 'Hello, world!', 'llama3.2', [
    'user_id' => $user->id
]);
```

#### Bypass Health Check

```php
// Skip health check (useful for testing or emergency operations)
$query = $dispatcher->dispatch('ollama', 'Hello!', null, [
    'skip_health_check' => true
]);
```

#### Allow Unhealthy Provider

```php
// Allow dispatch even if provider is unhealthy
$query = $dispatcher->dispatch('ollama', 'Hello!', null, [
    'allow_unhealthy' => true
]);
```

#### Automatic Fallback

```php
// Automatically fallback to healthy provider if primary fails
$query = $dispatcher->dispatchWithFallback(
    'ollama',                           // Primary provider
    'Hello, world!',                    // Prompt
    'llama3.2',                         // Model
    ['user_id' => $user->id],          // Options
    ['claude', 'lmstudio']             // Fallback providers
);

// The query will be dispatched to the first healthy provider
// Metadata will include fallback information
```

#### Find Healthy Provider

```php
// Get first healthy provider from list
$provider = $dispatcher->findHealthyProvider(['ollama', 'claude', 'lmstudio']);

if ($provider) {
    echo "Use {$provider}";
} else {
    echo "No healthy providers available";
}
```

#### Get Providers with Health Status

```php
// Get all providers enriched with health status
$providers = $dispatcher->getProvidersWithHealth();

foreach ($providers as $key => $provider) {
    echo "{$provider['name']}: {$provider['health']['status']}\n";
}
```

### 4. Dashboard Widget

**Location**: `/Volumes/JS-DEV/laravel-horizon-agent-workers/resources/views/components/provider-health-widget.blade.php`

A beautiful, auto-refreshing dashboard widget that displays provider health status.

#### Features

- Real-time status indicators with color coding
- Auto-refresh every 60 seconds
- Manual refresh button
- Detailed provider information
- Overall system status
- Responsive design

#### Usage

```blade
<!-- In any Blade view -->
<x-provider-health-widget />
```

The widget is automatically included in the dashboard at `/Volumes/JS-DEV/laravel-horizon-agent-workers/resources/views/dashboard.blade.php`.

## Provider-Specific Health Checks

### Claude API

Checks:
- API key configured
- API endpoint accessible
- Authentication valid

States:
- **Healthy**: API responds successfully
- **Unhealthy**: Missing API key, invalid credentials, or network error

### Ollama

Checks:
- Ollama service running (http://127.0.0.1:11434)
- Available models listed

States:
- **Healthy**: Service running with models loaded
- **Degraded**: Service running but no models found
- **Unhealthy**: Cannot connect to service

Configuration:
```bash
# .env
OLLAMA_BASE_URL=http://127.0.0.1:11434
```

### LM Studio

Checks:
- LM Studio server running (http://127.0.0.1:1234/v1)
- Models endpoint accessible
- At least one model loaded

States:
- **Healthy**: Server running with model loaded
- **Degraded**: Server running but no model loaded
- **Unhealthy**: Cannot connect to server

Configuration:
```bash
# .env
LMSTUDIO_BASE_URL=http://127.0.0.1:1234/v1
```

### Local Command

Checks:
- Shell executable available
- Command execution works
- Whitelisted CLI tools available (claude, ollama, llm, aider)

States:
- **Healthy**: Shell works and CLI tools found
- **Degraded**: Shell works but no CLI tools found
- **Unhealthy**: Shell not available or command execution fails

## Testing

### Run All Health Check Tests

```bash
# Service tests
php artisan test --filter=ProviderHealthCheckTest

# API endpoint tests
php artisan test --filter=ProviderHealthApiTest

# Integration tests
php artisan test --filter=LLMQueryDispatcherHealthCheckTest
```

### Manual Testing

```bash
# Check all providers via API
curl http://localhost:8000/api/providers/health

# Check specific provider
curl http://localhost:8000/api/providers/health/ollama

# Get summary
curl http://localhost:8000/api/providers/health/summary

# Clear cache (requires authentication)
curl -X POST http://localhost:8000/api/providers/health/clear-cache \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

## Error Handling and Logging

The system logs important events:

```php
// Degraded provider (warning)
Log::warning("Provider ollama is degraded but allowing dispatch");

// Unhealthy provider with fallback
Log::info("Provider ollama is unhealthy, suggesting fallback to claude");

// Fallback used
Log::info("Falling back from ollama to claude");
```

View logs:
```bash
php artisan pail --timeout=0
```

## Performance Considerations

1. **Caching**: Health checks are cached for 60 seconds by default to reduce overhead
2. **Timeouts**: Health check HTTP requests timeout after 5 seconds
3. **Async**: Health checks do not block job processing
4. **Rate Limiting**: Public health endpoints are rate limited to 60 requests/minute

## Configuration Files

- `/Volumes/JS-DEV/laravel-horizon-agent-workers/config/services.php` - Provider endpoints
- `/Volumes/JS-DEV/laravel-horizon-agent-workers/.env` - Environment variables

## Best Practices

1. **Always use fallback** for critical operations:
   ```php
   $query = $dispatcher->dispatchWithFallback('ollama', $prompt, null, $options);
   ```

2. **Monitor health status** in production using the dashboard widget

3. **Set up alerts** for when all providers are unhealthy

4. **Use appropriate cache TTL**:
   - Development: 30-60 seconds
   - Production: 60-120 seconds

5. **Handle exceptions** gracefully:
   ```php
   try {
       $query = $dispatcher->dispatch('ollama', $prompt);
   } catch (\RuntimeException $e) {
       // Provider unavailable - use fallback or notify user
   }
   ```

## Troubleshooting

### Provider Showing as Unhealthy

1. **Claude API**:
   - Check `ANTHROPIC_API_KEY` in `.env`
   - Verify API key is valid
   - Check network connectivity

2. **Ollama**:
   - Ensure Ollama is running: `ollama serve`
   - Pull models: `ollama pull llama3.2`
   - Check URL: `http://127.0.0.1:11434/api/tags`

3. **LM Studio**:
   - Start LM Studio
   - Enable local server in settings
   - Load a model
   - Check URL: `http://127.0.0.1:1234/v1/models`

4. **Local Command**:
   - Check shell is available: `echo $SHELL`
   - Install CLI tools: `npm install -g @anthropic-ai/claude-cli`
   - Verify tools: `which claude`

### Cache Issues

```bash
# Clear all provider health caches via API
curl -X POST http://localhost:8000/api/providers/health/clear-cache \
  -H "Authorization: Bearer TOKEN"

# Or via code
app(ProviderHealthCheck::class)->clearCache();
```

### High Response Times

- Increase cache TTL to reduce health check frequency
- Consider using a separate queue for health checks
- Monitor provider response times

## Future Enhancements

- [ ] Add webhook notifications for status changes
- [ ] Implement health check history/trends
- [ ] Add metrics and analytics
- [ ] Support custom health check intervals per provider
- [ ] Add circuit breaker pattern for failing providers
- [ ] Integrate with monitoring services (DataDog, NewRelic)

## Related Files

- Service: `/Volumes/JS-DEV/laravel-horizon-agent-workers/app/Services/ProviderHealthCheck.php`
- Controller: `/Volumes/JS-DEV/laravel-horizon-agent-workers/app/Http/Controllers/Api/ProviderHealthController.php`
- Dispatcher: `/Volumes/JS-DEV/laravel-horizon-agent-workers/app/Services/LLMQueryDispatcher.php`
- Routes: `/Volumes/JS-DEV/laravel-horizon-agent-workers/routes/api.php`
- Widget: `/Volumes/JS-DEV/laravel-horizon-agent-workers/resources/views/components/provider-health-widget.blade.php`
- Tests: `/Volumes/JS-DEV/laravel-horizon-agent-workers/tests/Feature/ProviderHealthCheckTest.php`
- Config: `/Volumes/JS-DEV/laravel-horizon-agent-workers/config/services.php`
