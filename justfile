# Enterprise ERP - Dev Command Runner

# List all available commands
default:
    @just --list

# --- Docker Control ---

# Start containers in background
up:
    docker compose up -d

# Stop containers
down:
    docker compose down

restart:
    docker compose restart

# Stop and destroy all containers, networks, and volumes
clean:
    docker compose down -v --remove-orphans

# View all logs
logs:
    docker compose logs -f

# View logs for specific container (e.g. just log app)
log service:
    docker compose logs -f {{service}}

# Shell into application container
shell:
    docker compose exec app bash

# --- Database & Tenancy ---

# Run central migrations and seed database
db-init:
    docker compose exec app php artisan migrate --force
    docker compose exec app php artisan db:seed --force

# Migrate central database
db-migrate:
    docker compose exec app php artisan migrate --force

# Rollback central database migration
db-rollback:
    docker compose exec app php artisan migrate:rollback

# Seed central database
db-seed:
    docker compose exec app php artisan db:seed --force

# Geo import (populates provinces/districts/communes/villages)
geodb-import:
    docker compose exec app php artisan geo:import

# Onboard new tenant (e.g. just tenant-create acme "Acme Corp")
tenant-create handle name:
    curl -X POST http://localhost:8000/api/tenants \
      -H "Content-Type: application/json" \
      -d "{\"name\":\"{{name}}\",\"handle\":\"{{handle}}\"}"

# Seed specific tenant database (e.g. just tenant-seed acme)
# tenant-seed handle:
#     docker compose exec app php artisan tenants:seed --tenants={{handle}}

# Migrate tenant database
tenant-migrate:
    docker compose exec app php artisan tenants:migrate

tenant-seed:
    docker compose exec app php artisan tenants:seed

# Clear all Laravel caches
cache-clear:
    docker compose exec app php artisan cache:clear
    docker compose exec app php artisan config:clear
    docker compose exec app php artisan route:clear
    docker compose exec app php artisan view:clear

# --- Testing ---

# Run Pest PHP backend tests
test-backend:
    docker compose exec app ./vendor/bin/pest

# Run Pest PHP backend tests with coverage
test-backend-cov:
    docker compose exec app ./vendor/bin/pest --coverage

# --- Frontend (Nuxt/Bun) ---

# Install frontend dependencies
fe-install:
    cd frontend && bun install

# Start frontend development server
fe-dev:
    cd frontend && bun dev

# Build frontend for production
fe-build:
    cd frontend && bun run build

# Run frontend tests
fe-test:
    cd frontend && bun test

# --- Agent & Skills ---

# Add/Sync agent skill (e.g. just skill-add pphatdev/erp-prompt iam)
skill-add repo skill:
    npx skills@latest add {{repo}} --skill {{skill}}
