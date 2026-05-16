# Docker Rules & Infrastructure Standards

## 1. General Principles
- **Containerization**: Every part of the ERP (Backend, Frontend, Workers) must be containerized for consistency across environments.
- **Base Images**: Use official, lightweight base images (e.g., `php:8.3-fpm-alpine`, `node:20-alpine`).
- **Immutability**: Docker images must be immutable once built; configuration changes are handled via environment variables.

## 2. Dockerfile Standards
- **Multi-Stage Builds**: Always use multi-stage builds to keep production images small and secure.
- **User Permissions**: Never run containers as `root`. Create a non-privileged user (e.g., `erp-user`).
- **Layers Optimization**: Group similar commands (`RUN apt-get update && apt-get install...`) to minimize layer count.
- **Exclusion**: Use `.dockerignore` to exclude `node_modules`, `vendor`, `.git`, and sensitive files like `.env`.

## 3. Service Configuration (Docker Compose)
- **Namespacing**: Services should be named logically: `api-service`, `client-app`, `postgres-db`, `redis-cache`.
- **Networks**: Use dedicated Docker networks (e.g., `erp-internal`, `erp-external`) to isolate database traffic from the public web.
- **Volumes**:
  - **Persistent**: Database data and file uploads must use named volumes.
  - **Bind Mounts**: Use bind mounts ONLY for local development to sync code.

## 4. Environment Variables
- **Secrets**: Never bake secrets into the Dockerfile. Use environment files (`.env`) or Docker Secrets/Config in production.
- **Overrides**: Use `docker-compose.override.yml` for developer-specific configurations (e.g., enabling Xdebug).

## 5. Persistence & Logging
- **Logs**: Containers must output logs to `stdout`/`stderr`. Log aggregation (e.g., ELK, Graylog) should handle collection.
- **Backups**: Database containers must have a scheduled sidecar or host-level job for automated dumps.

## 6. Health Checks
- **Automated Checks**: Each service must define a `HEALTHCHECK` in the Dockerfile or Compose file to ensure the system can detect and restart unhealthy containers.
- **Readiness**: API services must expose a `/health` endpoint that checks DB and Redis connectivity.

## 7. Standard Docker Compose Template (Local Development)
Every new ERP module or core setup must follow this standardized `docker-compose.yml` structure to ensure environment consistency.

```yaml
services:
  # Laravel 11+ API
  backend:
    build:
      context: ./backend
      dockerfile: Dockerfile
    container_name: erp_backend
    ports:
      - "8000:80"
    environment:
      - APP_ENV=local
      - APP_DEBUG=true
      - DB_CONNECTION=pgsql
      - DB_HOST=database
      - DB_PORT=5432
      - DB_DATABASE=${POSTGRES_DB}
      - DB_USERNAME=${POSTGRES_USER}
      - DB_PASSWORD=${POSTGRES_PASSWORD}
      - REDIS_HOST=cache
      - REDIS_PASSWORD=${REDIS_PASSWORD}
    volumes:
      - ./backend:/var/www
    depends_on:
      database:
        condition: service_healthy
      cache:
        condition: service_started
    networks:
      - erp_network

  # Nuxt 3+ Client App
  frontend:
    build:
      context: ./frontend
      dockerfile: Dockerfile
    container_name: erp_frontend
    ports:
      - "3000:3000"
    environment:
      - NUXT_PUBLIC_API_BASE=http://localhost:8000/api/v1
    volumes:
      - ./frontend:/src
    depends_on:
      - backend
    networks:
      - erp_network

  # PostgreSQL 17 Database
  database:
    image: postgres:17-alpine
    container_name: erp_database
    environment:
      POSTGRES_USER: ${POSTGRES_USER}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
      POSTGRES_DB: ${POSTGRES_DB}
    ports:
      - "5435:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${POSTGRES_USER} -d ${POSTGRES_DB}"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - erp_network

  # Redis Cache
  cache:
    image: redis:alpine
    container_name: erp_cache
    command: redis-server --requirepass ${REDIS_PASSWORD}
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    networks:
      - erp_network

  # pgAdmin for DB Management
  pgadmin:
    image: dpage/pgadmin4
    container_name: erp_pgadmin
    environment:
      PGADMIN_DEFAULT_EMAIL: ${PGADMIN_EMAIL:-admin@erp.local}
      PGADMIN_DEFAULT_PASSWORD: ${PGADMIN_PASSWORD:-admin123}
    ports:
      - "8080:80"
    volumes:
      - pgadmin_data:/var/lib/pgadmin
    networks:
      - erp_network

volumes:
  postgres_data:
  redis_data:
  pgadmin_data:

networks:
  erp_network:
    driver: bridge
```
