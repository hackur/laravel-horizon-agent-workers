# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel 12 application with Laravel Horizon integration for queue management and monitoring. The project uses:
- PHP 8.2+
- SQLite for database (default)
- Database-driven queue connection
- Vite for asset bundling with Tailwind CSS 4.0

## Development Commands

### Running the Application

```bash
# Start development environment (runs server, queue worker, logs, and Vite concurrently)
composer dev

# Individual services
php artisan serve              # Start development server
php artisan queue:listen --tries=1  # Start queue worker
php artisan pail --timeout=0   # Watch logs
npm run dev                    # Start Vite dev server
```

### Testing

```bash
# Run all tests
composer test
# or
php artisan test

# Run specific test suite
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Run specific test file
php artisan test tests/Unit/ExampleTest.php
```

### Code Quality

```bash
# Fix code style (Laravel Pint)
./vendor/bin/pint

# Test for code style errors without fixing
./vendor/bin/pint --test

# Stop on first error
./vendor/bin/pint --bail
```

### Horizon Commands

```bash
# Start Horizon supervisor
php artisan horizon

# Monitor Horizon status
php artisan horizon:status

# List supervisors
php artisan horizon:supervisors

# Pause/continue processing
php artisan horizon:pause
php artisan horizon:continue

# Clear queue metrics
php artisan horizon:clear-metrics

# Terminate Horizon (for restart)
php artisan horizon:terminate
```

### Database

```bash
# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Refresh database (drop all tables and re-run migrations)
php artisan migrate:fresh

# Seed database
php artisan db:seed
```

## Architecture

### Queue System

- **Default Connection**: Database (configurable via `QUEUE_CONNECTION` env variable)
- **Queue Table**: `jobs` (configurable via `DB_QUEUE_TABLE`)
- **Failed Jobs**: Stored in `failed_jobs` table with UUID driver
- **Job Batching**: Enabled with `job_batches` table

Horizon is installed but not yet configured. To configure Horizon, run `php artisan horizon:install` which will publish the configuration file and assets.

### Application Structure

```
app/
├── Http/Controllers/    # HTTP controllers
├── Models/             # Eloquent models
└── Providers/          # Service providers
```

The application uses Laravel 12's streamlined bootstrap configuration in `bootstrap/app.php` with routing, middleware, and exception handling configured there.

### Environment Configuration

- Uses SQLite database by default
- Queue connection: database
- Session driver: database
- Cache store: database
- Mail mailer: log (for development)

### Testing

PHPUnit is configured with:
- Unit tests in `tests/Unit/`
- Feature tests in `tests/Feature/`
- In-memory SQLite database for testing
- Sync queue connection during tests

## Key Files

- `bootstrap/app.php` - Application bootstrap and configuration
- `routes/web.php` - Web routes
- `routes/console.php` - Console command definitions
- `config/queue.php` - Queue configuration
- `.env.example` - Environment variable template

## Notes

- The project name "laravel-horizon-agent-workers" suggests this is for experimenting with Horizon's agent-based worker management
- No custom jobs, agents, or workers are implemented yet - this is a fresh Laravel installation with Horizon added
- Horizon configuration needs to be published before customizing supervisor/worker settings
