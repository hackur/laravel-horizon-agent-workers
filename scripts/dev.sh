#!/usr/bin/env bash

# dev.sh - Developer utility script for Laravel Horizon LLM Agent Workers
#
# This script provides a friendly CLI to manage your local development
# environment end-to-end: dependencies, fresh install, services, assets,
# telemetry (Telescope), queues (Horizon), websockets (Reverb), tests, and more.
#
# Usage:
#   bash scripts/dev.sh [command]
#   ./scripts/dev.sh [command]   # after `chmod +x scripts/dev.sh`
#
# Common commands:
#   help         Show all commands and examples
#   check        Verify dependencies (PHP, Composer, Redis, Node)
#   bootstrap    Install deps, copy .env, generate key
#   fresh        Drop DB, migrate, seed sample data
#   start        Start app, Horizon, Reverb (or Composer dev concurrently)
#   stop         Stop app and queue workers
#   migrate      Run database migrations
#   seed         Seed the database
#   build        Build frontend assets (Vite)
#   logs         Tail app logs (Pail)
#   status       Show process/health status
#   test         Run the test suite
#   telescope    Install Telescope and migrate

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

info() { echo -e "${BLUE}ℹ${NC} $*"; }
success() { echo -e "${GREEN}✓${NC} $*"; }
warn() { echo -e "${YELLOW}⚠${NC} $*"; }
error() { echo -e "${RED}✗${NC} $*"; }

command_exists() { command -v "$1" >/dev/null 2>&1; }

check_deps() {
  info "Checking dependencies..."

  local ok=true

  if command_exists php; then
    success "PHP $(php -r 'echo PHP_VERSION;')"
  else
    error "PHP not found"
    ok=false
  fi

  if command_exists composer; then
    success "Composer $(composer --version | awk '{print $3}')"
  else
    error "Composer not found"
    ok=false
  fi

  if command_exists redis-cli; then
    if redis-cli ping >/dev/null 2>&1; then
      success "Redis running"
    else
      warn "Redis installed but not running (brew services start redis)"
      ok=false
    fi
  else
    warn "Redis not found (brew install redis)"
    ok=false
  fi

  if command_exists node; then
    success "Node $(node --version)"
  else
    warn "Node.js not found (optional for frontend)"
  fi

  if [ "$ok" = true ]; then
    success "All required dependencies satisfied"
  else
    error "Missing required dependencies"
    return 1
  fi
}

bootstrap() {
  info "Bootstrapping project (Composer, Node, .env, key)..."

  check_deps || true

  if [ ! -f .env ]; then
    info "Creating .env from .env.example"
    cp .env.example .env
  fi

  info "Installing PHP dependencies"
  composer install --no-interaction

  if command_exists npm; then
    info "Installing Node dependencies"
    npm install
  else
    warn "Skipping Node install (npm not found)"
  fi

  info "Generating app key"
  php artisan key:generate || true

  success "Bootstrap complete"
}

fresh() {
  info "Fresh install: migrate:fresh + seed"
  check_deps || true
  php artisan migrate:fresh --seed
  success "Fresh install complete"
  info "Login: admin@example.com / password"
}

start() {
  info "Starting development environment"
  check_deps || true

  # Ensure Redis
  if ! redis-cli ping >/dev/null 2>&1; then
    if command_exists brew; then
      info "Starting Redis via brew services"
      brew services start redis || true
      sleep 2
    else
      warn "Install/start Redis manually"
    fi
  fi

  # Prefer Composer dev script (server + queue + logs + vite)
  if grep -q '"dev"' composer.json; then
    info "Launching Composer dev script (concurrently)"
    composer run dev
    return 0
  fi

  info "Starting app, Horizon, and Reverb"
  trap 'info "Stopping services"; kill 0' INT
  php artisan serve &
  php artisan horizon &
  php artisan reverb:start &
  wait
}

stop() {
  info "Stopping services"
  pkill -f "artisan serve" || true
  php artisan horizon:terminate || true
  success "Stopped"
}

migrate() { info "Migrations"; php artisan migrate; success "Done"; }
seed() { info "Seeding"; php artisan db:seed; success "Done"; }

build() {
  if command_exists npm; then
    info "Building assets (Vite)"
    npm run build
    success "Assets built"
  else
    warn "npm not found; skipping build"
  fi
}

logs() {
  if [ -f vendor/bin/pail ] || composer show -N | grep -q laravel/pail; then
    info "Tailing app logs (Pail)"
    php artisan pail --timeout=0
  else
    warn "Pail not installed; use tail -f storage/logs/laravel.log"
    tail -f storage/logs/laravel.log
  fi
}

status() {
  info "Status"
  pgrep -f "artisan serve" >/dev/null && success "Server running" || warn "Server stopped"
  pgrep -f "artisan horizon" >/dev/null && success "Horizon running" || warn "Horizon stopped"
  redis-cli ping >/dev/null 2>&1 && success "Redis running" || error "Redis not running"
  php artisan horizon:status 2>/dev/null || warn "Start Horizon: php artisan horizon"
}

test() { info "Tests"; php artisan test; }

telescope() {
  info "Installing Telescope"
  composer require laravel/telescope --dev || true
  php artisan telescope:install
  php artisan migrate
  success "Telescope ready at /telescope"
}

help() {
  cat <<'EOF'
Laravel Horizon LLM Agent Workers - Dev Utilities

Usage:
  scripts/dev.sh [command]

Commands:
  check        Verify required dependencies
  bootstrap    Composer/npm install, create .env, generate key
  fresh        Reset database and seed sample data
  start        Start app, Horizon, Reverb (or composer dev)
  stop         Stop services
  migrate      Run migrations
  seed         Seed database
  build        Build frontend assets (Vite)
  logs         Tail logs (Pail)
  status       Show running services
  test         Run tests
  telescope    Install Telescope, run migrations
  help         Show this help

Examples:
  scripts/dev.sh bootstrap
  scripts/dev.sh fresh
  scripts/dev.sh start
  scripts/dev.sh status
  scripts/dev.sh telescope
EOF
}

cmd=${1:-help}
case "$cmd" in
  check) check_deps ;;
  bootstrap) bootstrap ;;
  fresh) fresh ;;
  start) start ;;
  stop) stop ;;
  migrate) migrate ;;
  seed) seed ;;
  build) build ;;
  logs) logs ;;
  status) status ;;
  test) test ;;
  telescope) telescope ;;
  help|*) help ;;
esac

