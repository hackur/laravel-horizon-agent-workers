# Laravel Horizon LLM Agent Workers - Setup Guide

This Laravel application integrates Laravel Horizon with multiple LLM providers to run AI queries as background worker jobs.

## Features

✅ Multiple LLM Provider Support:
- **LM Studio** (Local OpenAI-compatible server at http://127.0.0.1:1234/v1) - **Default for fresh installs**
- **Local Command** (Execute any CLI command with your shell profile - claude, curl, python, etc.) - **RECOMMENDED for Claude Code**
- **Claude API** (Anthropic API)
- **Ollama** (Local LLM)
- **Claude Code CLI** (Command-line interface)

✅ **Conversation Threading** - Multi-turn conversations with context
✅ **Reasoning Content Display** - View AI's thinking process (for supported models)
✅ **Usage Statistics** - Token counts and performance metrics
✅ Queue Management via Laravel Horizon
✅ Web Interface for query management
✅ RESTful API endpoints
✅ Artisan CLI commands
✅ Real-time monitoring and metrics
✅ **Real-Time WebSocket Updates** - Live query status and conversation updates via Laravel Reverb

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
# LM Studio query (DEFAULT - requires LM Studio running at http://127.0.0.1:1234/v1)
php artisan llm:query lmstudio "Write a haiku about code"

# Claude API query
php artisan llm:query claude "Explain quantum computing" --model=claude-3-5-sonnet-20241022

# Ollama query
php artisan llm:query ollama "What is machine learning?" --model=llama3.2

# Claude Code CLI query (original method)
php artisan llm:query claude-code "Explain this codebase"

# Local Command Execution (RECOMMENDED for Claude Code - uses your zsh profile & auth)
php artisan llm:query local-command "What is 2+2?" --command="claude"

# Local Command with custom command (any CLI tool)
php artisan llm:query local-command "print('Hello')" --command="python3 -c"
php artisan llm:query local-command "https://example.com" --command="curl -s"
```

**Note**: The `local-command` provider runs commands in your login shell (`zsh -l -c`) which loads your `.zprofile` and `.zshrc`, giving access to your Claude Code authentication tokens, custom PATH, and other shell configuration.

### Conversation System

The application includes a full conversation threading system for multi-turn interactions with LLMs:

#### Creating a Conversation

1. **Via Web Dashboard**:
   - Login at `http://localhost:8000`
   - Click "New Conversation" button
   - Choose your provider (lmstudio, claude, ollama, local-command, etc.)
   - Select a model (optional)
   - Enter your first message
   - Click "Start Conversation"

2. **Dashboard Features**:
   - View all your conversations with last message preview
   - See message count and timestamps
   - Search conversations by title
   - Filter by provider
   - Quick stats: total conversations, queries, completed/pending counts

#### Continuing a Conversation

1. Click on any conversation from your dashboard
2. View the full message history with:
   - User messages
   - AI responses
   - Reasoning content (for supported models like Magistral)
   - Token usage statistics
   - Response times
3. Add new messages using the form at the bottom
4. Context from previous messages is automatically included

#### Viewing LLM Responses

When viewing a query response, you'll see:
- **Response**: The AI's answer
- **Reasoning Content**: The AI's thinking process (collapsible, for models that support it)
- **Usage Statistics**:
  - Prompt tokens
  - Completion tokens
  - Total tokens
  - Cache read tokens (if applicable)
- **Metadata**: Duration, finish reason, model used

#### Example Workflow

```bash
# 1. Run fresh installation
./dev.sh fresh

# 2. Start services
./dev.sh start

# 3. Login to dashboard
# http://localhost:8000
# Email: admin@example.com
# Password: password

# 4. View seeded conversations (3 sample conversations with multiple messages)
# 5. Create new conversation and add messages
# 6. View reasoning content for LM Studio queries with Magistral model
```

### Real-Time WebSocket Updates

The application includes **Laravel Reverb** for real-time WebSocket updates, providing instant feedback as LLM queries are processed without requiring page refreshes.

#### What Updates in Real-Time

✅ **Query Status Changes**:
- When a query starts processing
- When a query completes
- When a query fails
- Progress updates and notifications

✅ **Conversation Messages**:
- New AI responses appear instantly
- Live status indicators show connection state
- Automatic scroll to new messages
- Toast notifications for updates

#### How It Works

The application uses:
- **Laravel Reverb** - First-party WebSocket server (port 8080)
- **Laravel Echo** - JavaScript library for subscribing to channels
- **Private Channels** - Secured channels with user authentication
- **Event Broadcasting** - Real-time events dispatched from background jobs

When a query is processed:
1. BaseLLMJob broadcasts `QueryStatusUpdated` events at each lifecycle stage
2. When conversation messages are created, `MessageReceived` events are broadcast
3. Your browser listens on private channels for updates
4. The UI updates automatically with smooth animations

#### Prerequisites

Reverb must be running alongside your other services. It starts automatically with:

```bash
# Using overmind (recommended)
overmind start

# Or using dev.sh
./dev.sh start
```

Reverb runs on port **8080** by default.

#### Visual Indicators

When WebSocket connection is active, you'll see:
- **Green "Live Updates Active" indicator** on query pages
- **Green "Connected - Real-time updates active" banner** on conversation pages
- **Animated pulse indicators** showing active connection
- **Toast notifications** when updates occur

#### Configuration

WebSocket settings in `.env`:

```env
# Broadcasting Configuration
BROADCAST_CONNECTION=reverb

# Reverb WebSocket Server
REVERB_APP_ID=523602
REVERB_APP_KEY=pog1jxsss5m1yvetjcff
REVERB_APP_SECRET=vmd50dyvt3gmasfhvwjr
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

# Vite needs these for frontend
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

#### Testing WebSocket Updates

1. **Start all services** (Reverb must be running):
   ```bash
   ./dev.sh start
   # Or: overmind start
   ```

2. **Open a conversation page** in your browser:
   ```
   http://localhost:8000/conversations/{id}
   ```

3. **Look for the green "Connected" indicator** - this confirms WebSocket is active

4. **Send a message** using the form at the bottom

5. **Watch the page** - the AI response will appear automatically when ready, with:
   - No page refresh needed
   - Smooth fade-in animation
   - Live status updates
   - Toast notification

6. **For query testing**, dispatch a query and keep the query detail page open:
   ```bash
   php artisan llm:query lmstudio "Test real-time updates"
   ```

   Then visit `http://localhost:8000/llm-queries/{id}` - the page will update automatically as the query processes.

#### Channel Security

WebSocket channels are secured with private channel authorization:

- **`queries.{id}`** - Only the query owner can subscribe
- **`conversations.{id}`** - Only the conversation owner can subscribe

Authorization logic is in `routes/channels.php`:
```php
Broadcast::channel('queries.{id}', function ($user, $id) {
    return $user->id === \App\Models\LLMQuery::findOrFail($id)->user_id;
});

Broadcast::channel('conversations.{id}', function ($user, $id) {
    return $user->id === \App\Models\Conversation::findOrFail($id)->user_id;
});
```

#### Troubleshooting WebSocket Issues

**No live indicator appearing?**
```bash
# 1. Check Reverb is running
lsof -i :8080

# 2. Check browser console for Echo connection
# Open DevTools → Console
# Should see Echo connecting to ws://localhost:8080

# 3. Restart Reverb
pkill -f "reverb:start"
php artisan reverb:start
```

**Updates not appearing?**
```bash
# 1. Verify BROADCAST_CONNECTION in .env
grep BROADCAST_CONNECTION .env
# Should show: BROADCAST_CONNECTION=reverb

# 2. Clear config cache
php artisan config:clear

# 3. Rebuild frontend assets
npm run build
```

**WebSocket connection refused?**
```bash
# Check if Reverb is listening
netstat -an | grep 8080
# Should show LISTEN on port 8080

# Check Reverb logs
# Look for "Starting server on 0.0.0.0:8080"
```

**Authentication failing on private channels?**
- Ensure you're logged in (WebSockets require authentication)
- Check `routes/channels.php` authorization logic
- Verify the query/conversation belongs to the logged-in user

#### Technical Details

**Events:**
- `App\Events\QueryStatusUpdated` - Broadcasts query lifecycle changes
- `App\Events\MessageReceived` - Broadcasts new conversation messages

**Channels:**
- Private channel format: `private-queries.{id}` and `private-conversations.{id}`
- Echo automatically handles "private-" prefix

**Broadcasting Points:**
- `app/Jobs/LLM/BaseLLMJob.php` broadcasts at:
  - Job start (status: processing)
  - Job completion (status: completed, includes response)
  - Job failure (status: failed, includes error)

**Frontend:**
- `resources/js/bootstrap.js` - Echo configuration
- `resources/views/conversations/show.blade.php` - Message listener
- `resources/views/llm-queries/show.blade.php` - Status listener

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
┌─────────────────────────────────────────────────────┐
│              Web Browser / CLI                      │
│  ┌─────────────────┐      ┌────────────────────┐   │
│  │  Web Interface  │◄────►│  Laravel Echo      │   │
│  │  /CLI Command   │      │  (WebSocket Client)│   │
│  └─────────┬───────┘      └──────▲─────────────┘   │
└────────────┼─────────────────────┼─────────────────┘
             │                     │
             ▼                     │ WebSocket
┌────────────────────────┐         │ (port 8080)
│  LLMQueryDispatcher    │         │
└────────┬───────────────┘         │
         │                         │
         ▼                         │
┌─────────────────────────────────┼──┐
│      Queue System (Redis)       │  │
│  ┌──────────────────────────┐   │  │
│  │ llm-claude queue         │   │  │
│  │ llm-ollama queue         │   │  │
│  │ llm-local queue          │   │  │
│  └──────────────────────────┘   │  │
└────────┬────────────────────────┼──┘
         │                        │
         ▼                        │
┌─────────────────────────────────┼──────┐
│   Laravel Horizon Workers       │      │
│  ┌──────────────────────────┐   │      │
│  │ ClaudeQueryJob           │   │      │
│  │ OllamaQueryJob           │   │      │
│  │ LMStudioQueryJob         │   │      │
│  │ ClaudeCodeQueryJob       │───┼──────┤
│  │                          │   │      │ Broadcasts
│  │ → QueryStatusUpdated     │───┼──────┤ Events
│  │ → MessageReceived        │   │      │
│  └──────────────────────────┘   │      │
└────────┬────────────────────────┼──────┘
         │                        │
         ▼                        │
┌─────────────────────────────────┼──────┐
│   External LLM Providers        │      │
│  ┌──────────────────────────┐   │      │
│  │ Anthropic API            │   │      │
│  │ Ollama (localhost:11434) │   │      │
│  │ LM Studio (localhost:1234)│  │      │
│  │ Claude Code CLI          │   │      │
│  └──────────────────────────┘   │      │
└─────────────────────────────────┘      │
                                         │
         ┌───────────────────────────────┘
         │
         ▼
┌─────────────────────────────────┐
│   Laravel Reverb                │
│   (WebSocket Server)            │
│   → queries.{id} channel        │
│   → conversations.{id} channel  │
└─────────────────────────────────┘
```

## File Structure

```
app/
├── Console/Commands/
│   └── LLMQueryCommand.php        # CLI command for dispatching queries
├── Events/                        # NEW: WebSocket broadcast events
│   ├── QueryStatusUpdated.php     # Broadcasts query status changes
│   └── MessageReceived.php        # Broadcasts new conversation messages
├── Http/Controllers/
│   ├── ConversationController.php # Conversation management
│   └── LLMQueryController.php     # Web & API controller
├── Jobs/LLM/
│   ├── BaseLLMJob.php             # Base job class (with broadcasting)
│   ├── Claude/
│   │   └── ClaudeQueryJob.php     # Claude API job
│   ├── Ollama/
│   │   └── OllamaQueryJob.php     # Ollama job
│   ├── LMStudio/
│   │   └── LMStudioQueryJob.php   # LM Studio job
│   ├── LocalCommandJob.php        # Local command execution job
│   └── ClaudeCodeQueryJob.php     # Claude Code CLI job
├── Listeners/
│   └── UpdateLLMQueryStatus.php   # Job event listener
├── Models/
│   ├── Conversation.php           # Conversation model
│   ├── ConversationMessage.php    # Message model
│   ├── LLMQuery.php               # Eloquent model
│   └── User.php                   # User model (Jetstream)
├── Providers/
│   ├── AppServiceProvider.php     # Event registration
│   └── HorizonServiceProvider.php # Horizon auth config
└── Services/
    ├── ConversationService.php    # Conversation logic
    └── LLMQueryDispatcher.php     # Job dispatcher service

config/
├── broadcasting.php               # Broadcasting configuration
├── horizon.php                    # Horizon queue configuration
├── queue.php                      # Queue connection settings
└── reverb.php                     # Reverb WebSocket server config

resources/
├── js/
│   ├── app.js                     # Main JavaScript
│   └── bootstrap.js               # Echo configuration (WebSocket)
└── views/
    ├── conversations/
    │   ├── index.blade.php        # Conversations dashboard
    │   └── show.blade.php         # Conversation detail (with WebSocket listener)
    └── llm-queries/
        ├── index.blade.php        # Query list view
        ├── create.blade.php       # Create query form
        └── show.blade.php         # Query detail (with WebSocket listener)

routes/
├── channels.php                   # WebSocket channel authorization
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

- ~~Add authentication for the web interface~~ ✅ **DONE** (Laravel Jetstream)
- ~~Real-time WebSocket updates~~ ✅ **DONE** (Laravel Reverb)
- ~~Conversation threading system~~ ✅ **DONE**
- Implement job batching for multiple prompts
- Add streaming response support
- Create job retry strategies
- Add rate limiting for API providers
- Implement webhook notifications for job completion
- Add cost tracking for paid APIs
- Add markdown rendering for AI responses
- Implement conversation search and filtering
- Add export functionality for conversations

## Resources

- [Laravel Horizon Documentation](https://laravel.com/docs/12.x/horizon)
- [Laravel Reverb Documentation](https://laravel.com/docs/12.x/reverb)
- [Laravel Broadcasting Documentation](https://laravel.com/docs/12.x/broadcasting)
- [Anthropic API Documentation](https://docs.anthropic.com/)
- [Ollama Documentation](https://ollama.ai/docs)
- [LM Studio Documentation](https://lmstudio.ai/docs)
