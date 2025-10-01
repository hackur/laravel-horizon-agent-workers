#!/bin/bash

# dev.sh - Laravel Horizon LLM Agent Workers Development Helper
# Usage: ./dev.sh [command]

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Helper functions
info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

success() {
    echo -e "${GREEN}✓${NC} $1"
}

error() {
    echo -e "${RED}✗${NC} $1"
}

warn() {
    echo -e "${YELLOW}⚠${NC} $1"
}

# Check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Check dependencies
check_deps() {
    info "Checking dependencies..."

    local all_ok=true

    # Check PHP
    if command_exists php; then
        PHP_VERSION=$(php -r "echo PHP_VERSION;")
        success "PHP ${PHP_VERSION} installed"
    else
        error "PHP is not installed"
        all_ok=false
    fi

    # Check Composer
    if command_exists composer; then
        COMPOSER_VERSION=$(composer --version | awk '{print $3}')
        success "Composer ${COMPOSER_VERSION} installed"
    else
        error "Composer is not installed"
        all_ok=false
    fi

    # Check Redis
    if command_exists redis-cli; then
        if redis-cli ping >/dev/null 2>&1; then
            success "Redis is running"
        else
            warn "Redis is installed but not running"
            info "Start with: brew services start redis"
            all_ok=false
        fi
    else
        error "Redis is not installed"
        info "Install with: brew install redis"
        all_ok=false
    fi

    # Check Node/NPM (optional)
    if command_exists node; then
        NODE_VERSION=$(node --version)
        success "Node ${NODE_VERSION} installed"
    else
        warn "Node.js is not installed (optional for frontend)"
    fi

    # Check optional LLM tools
    echo ""
    info "Optional LLM providers:"

    if command_exists ollama; then
        success "Ollama is installed"
    else
        warn "Ollama not found (install from https://ollama.ai)"
    fi

    if [ -d "/Applications/LM Studio.app" ]; then
        success "LM Studio is installed"
    else
        warn "LM Studio not found (install from https://lmstudio.ai)"
    fi

    if command_exists claude; then
        success "Claude Code CLI is installed"
    else
        warn "Claude Code CLI not found"
    fi

    echo ""

    if [ "$all_ok" = false ]; then
        error "Some required dependencies are missing!"
        exit 1
    fi

    success "All required dependencies are installed!"
}

# Fresh install
fresh() {
    info "Running fresh installation..."

    check_deps

    info "Dropping all tables and running migrations..."
    php artisan migrate:fresh

    info "Running seeders..."
    php artisan db:seed

    success "Fresh installation complete!"
    echo ""
    info "Default LLM provider for fresh installs: LM Studio"
    info "Make sure LM Studio is running at http://127.0.0.1:1234/v1"
    info "Test with: php artisan llm:query lmstudio \"Hello world\""
    echo ""
    info "Login credentials:"
    echo "  Admin: admin@example.com / password"
    echo "  Test:  test@example.com / password"
}

# Start development environment
start() {
    info "Starting development environment..."

    check_deps

    # Start Redis if not running
    if ! redis-cli ping >/dev/null 2>&1; then
        info "Starting Redis..."
        brew services start redis
        sleep 2
    fi

    # Check if overmind is installed
    if command_exists overmind; then
        info "Starting services with overmind..."
        info "Press Ctrl+C to stop all services"
        echo ""
        overmind start
    else
        warn "Overmind not installed, falling back to basic start"
        info "Install overmind with: brew install overmind"
        info "Starting Laravel application and Horizon..."
        info "Press Ctrl+C to stop all services"
        echo ""

        # Use trap to handle cleanup on Ctrl+C
        trap 'info "Stopping services..."; kill 0' INT

        # Start services in background
        php artisan serve &
        php artisan horizon &
        php artisan reverb:start &

        # Wait for background processes
        wait
    fi
}

# Stop services
stop() {
    info "Stopping services..."

    pkill -f "artisan serve" || true
    php artisan horizon:terminate || true

    success "Services stopped"
}

# Run migrations
migrate() {
    info "Running migrations..."
    php artisan migrate
    success "Migrations complete!"
}

# Seed database
seed() {
    info "Seeding database..."
    php artisan db:seed
    success "Database seeded!"
}

# Clear caches
clear_cache() {
    info "Clearing caches..."
    php artisan cache:clear
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    success "Caches cleared!"
}

# Run tests
test() {
    info "Running tests..."
    php artisan test
}

# Show status
status() {
    info "System Status:"
    echo ""

    # Check if services are running
    if pgrep -f "artisan serve" >/dev/null; then
        success "Laravel server is running"
    else
        warn "Laravel server is NOT running"
    fi

    if pgrep -f "artisan horizon" >/dev/null; then
        success "Horizon is running"
    else
        warn "Horizon is NOT running"
    fi

    if redis-cli ping >/dev/null 2>&1; then
        success "Redis is running"
    else
        error "Redis is NOT running"
    fi

    echo ""
    php artisan horizon:status 2>/dev/null || warn "Run 'php artisan horizon' to start queue workers"
}

# Show help
help() {
    echo "Laravel Horizon LLM Agent Workers - Development Helper"
    echo ""
    echo "Usage: ./dev.sh [command]"
    echo ""
    echo "Commands:"
    echo "  check        Check if all dependencies are installed"
    echo "  fresh        Fresh install (migrate:fresh + seed)"
    echo "  start        Start development environment (serve + horizon)"
    echo "  stop         Stop all services"
    echo "  migrate      Run database migrations"
    echo "  seed         Seed the database"
    echo "  clear        Clear all Laravel caches"
    echo "  test         Run tests"
    echo "  status       Show status of services"
    echo "  help         Show this help message"
    echo ""
    echo "Examples:"
    echo "  ./dev.sh check         # Check dependencies"
    echo "  ./dev.sh fresh         # Fresh install with sample data"
    echo "  ./dev.sh start         # Start dev environment"
    echo ""
}

# Main command handler
case "${1:-help}" in
    check)
        check_deps
        ;;
    fresh)
        fresh
        ;;
    start)
        start
        ;;
    stop)
        stop
        ;;
    migrate)
        migrate
        ;;
    seed)
        seed
        ;;
    clear)
        clear_cache
        ;;
    test)
        test
        ;;
    status)
        status
        ;;
    help|*)
        help
        ;;
esac
