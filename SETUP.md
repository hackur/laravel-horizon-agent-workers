# Laravel Horizon LLM Agent Workers - Setup Guide

This Laravel application integrates Laravel Horizon with multiple LLM providers to run AI queries as background worker jobs.

## Features

✅ Multiple LLM Provider Support:
- **Claude API** (Anthropic API)
- **Ollama** (Local LLM)
- **LM Studio** (Local OpenAI-compatible server)
- **Claude Code CLI** (Command-line interface)

✅ Queue Management via Laravel Horizon
✅ Web Interface for query management
✅ RESTful API endpoints
✅ Artisan CLI commands
✅ Real-time monitoring and metrics

## Prerequisites

1. **PHP 8.2+**
2. **Redis** - Required for Horizon (install via Homebrew: `brew install redis`)
3. **Composer**
4. **Node.js & NPM** (optional, for frontend assets)

### Optional (for specific providers):
- **Anthropic API Key** - For Claude API queries
- **Ollama** - Install from https://ollama.ai for local LLM
- **LM Studio** - Install from https://lmstudio.ai for local models
- **Claude Code CLI** - Install for Claude Code functionality

## Installation & Setup

### 1. Start Redis
```bash
# Start Redis server
brew services start redis

# Or run manually
redis-server
```

### 2. Configure Environment

Edit `.env` file and add your API keys:

```env
# Queue Configuration
QUEUE_CONNECTION=redis

# Redis Configuration
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Anthropic API (for Claude)
ANTHROPIC_API_KEY=your_api_key_here

# Ollama Configuration (if using Ollama)
OLLAMA_BASE_URL=http://localhost:11434

# LM Studio Configuration (if using LM Studio)
LMSTUDIO_BASE_URL=http://localhost:1234
```

To publish Anthropic and Ollama config files:
```bash
php artisan vendor:publish --provider="Anthropic\Laravel\AnthropicServiceProvider"
php artisan vendor:publish --provider="CloudStudio\Ollama\OllamaServiceProvider"
```

### 3. Run Migrations
```bash
php artisan migrate
```

### 4. Start Horizon
```bash
php artisan horizon
```

### 5. Start the Application (in another terminal)
```bash
php artisan serve
```

## Usage

### Web Interface

1. Access the application at `http://localhost:8000`
2. Create new queries via the web form
3. Monitor job status in real-time
4. View Horizon dashboard at `http://localhost:8000/horizon`

### CLI Commands

Dispatch a query via command line:

```bash
# Claude API query
php artisan llm:query claude "Explain quantum computing" --model=claude-3-5-sonnet-20241022

# Ollama query
php artisan llm:query ollama "What is machine learning?" --model=llama3.2

# LM Studio query
php artisan llm:query lmstudio "Write a haiku about code"

# Claude Code CLI query
php artisan llm:query claude-code "Explain this codebase"
```

### API Endpoints

#### Create a query
```bash
curl -X POST http://localhost:8000/api/llm/query \
  -H "Content-Type: application/json" \
  -d '{
    "provider": "claude",
    "prompt": "Explain recursion",
    "model": "claude-3-5-sonnet-20241022"
  }'
```

#### Get query by ID
```bash
curl http://localhost:8000/api/llm/query/1
```

#### List all queries
```bash
curl http://localhost:8000/api/llm/queries
```

## Queue Configuration

The application uses 4 dedicated queues (configured in `config/horizon.php`):

- **llm-claude** - For Claude API jobs (2 workers, 300s timeout)
- **llm-ollama** - For Ollama jobs (3 workers, 600s timeout)
- **llm-local** - For LM Studio and Claude Code jobs (2 workers, 900s timeout)
- **default** - For general jobs (3 workers, 60s timeout)

## Monitoring

### Horizon Dashboard
Access at `http://localhost:8000/horizon` to:
- View real-time job throughput
- Monitor queue wait times
- Track job failures and retries
- See recent and failed jobs
- View metrics and trends

### Database Records
All queries are persisted in the `l_l_m_queries` table with:
- Provider and model information
- Prompt and response
- Status (pending, processing, completed, failed)
- Duration and timestamps
- Error messages (if failed)

## Architecture

```
┌─────────────────┐
│  Web Interface  │
│   /CLI Command  │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ LLMQueryDispatcher│
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────┐
│      Queue System (Redis)        │
│  ┌──────────────────────────┐   │
│  │ llm-claude queue         │   │
│  │ llm-ollama queue         │   │
│  │ llm-local queue          │   │
│  └──────────────────────────┘   │
└────────┬────────────────────────┘
         │
         ▼
┌─────────────────────────────────┐
│   Laravel Horizon Workers        │
│  ┌──────────────────────────┐   │
│  │ ClaudeQueryJob           │   │
│  │ OllamaQueryJob           │   │
│  │ LMStudioQueryJob         │   │
│  │ ClaudeCodeQueryJob       │   │
│  └──────────────────────────┘   │
└────────┬────────────────────────┘
         │
         ▼
┌─────────────────────────────────┐
│   External LLM Providers         │
│  ┌──────────────────────────┐   │
│  │ Anthropic API            │   │
│  │ Ollama (localhost:11434) │   │
│  │ LM Studio (localhost:1234)│   │
│  │ Claude Code CLI          │   │
│  └──────────────────────────┘   │
└─────────────────────────────────┘
```

## File Structure

```
app/
├── Console/Commands/
│   └── LLMQueryCommand.php        # CLI command for dispatching queries
├── Http/Controllers/
│   └── LLMQueryController.php     # Web & API controller
├── Jobs/LLM/
│   ├── BaseLLMJob.php             # Base job class
│   ├── Claude/
│   │   └── ClaudeQueryJob.php     # Claude API job
│   ├── Ollama/
│   │   └── OllamaQueryJob.php     # Ollama job
│   ├── LMStudio/
│   │   └── LMStudioQueryJob.php   # LM Studio job
│   └── ClaudeCodeQueryJob.php     # Claude Code CLI job
├── Listeners/
│   └── UpdateLLMQueryStatus.php   # Job event listener
├── Models/
│   └── LLMQuery.php               # Eloquent model
├── Providers/
│   ├── AppServiceProvider.php     # Event registration
│   └── HorizonServiceProvider.php # Horizon auth config
└── Services/
    └── LLMQueryDispatcher.php     # Job dispatcher service

config/
├── horizon.php                    # Horizon queue configuration
└── queue.php                      # Queue connection settings

resources/views/llm-queries/
├── index.blade.php                # Query list view
├── create.blade.php               # Create query form
└── show.blade.php                 # Query detail view

routes/
└── web.php                        # Web and API routes
```

## Troubleshooting

### Redis Connection Error
```
Ensure Redis is running: redis-cli ping
Should return: PONG
```

### Horizon Not Processing Jobs
```bash
# Restart Horizon
php artisan horizon:terminate
php artisan horizon
```

### View Horizon Status
```bash
php artisan horizon:status
```

### Check Queue Status
```bash
php artisan queue:monitor
```

### Clear Failed Jobs
```bash
php artisan horizon:clear
```

## Development Tips

1. **Test Queue Locally**: Use `php artisan queue:work` for debugging instead of Horizon
2. **Monitor Logs**: Run `php artisan pail` to watch application logs in real-time
3. **Inspect Jobs**: Use Laravel Tinker to inspect database records
   ```bash
   php artisan tinker
   > App\Models\LLMQuery::latest()->first()
   ```

## Next Steps

- Add authentication for the web interface
- Implement job batching for multiple prompts
- Add streaming response support
- Create job retry strategies
- Add rate limiting for API providers
- Implement webhook notifications for job completion
- Add cost tracking for paid APIs

## Resources

- [Laravel Horizon Documentation](https://laravel.com/docs/12.x/horizon)
- [Anthropic API Documentation](https://docs.anthropic.com/)
- [Ollama Documentation](https://ollama.ai/docs)
- [LM Studio Documentation](https://lmstudio.ai/docs)
