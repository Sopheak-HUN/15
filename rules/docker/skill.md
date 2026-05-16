# Skill: Docker Infrastructure & Containerization

## Context
Use this skill when creating or modifying Dockerfiles, updating Docker Compose configurations, or troubleshooting environment issues. This ensures that the ERP infrastructure is secure, scalable, and reproducible across local development, staging, and production.

## Guidelines

### 1. Image Construction
- **PHP/Laravel**: Use `php:8.3-fpm-alpine`. Ensure PHP extensions required by Laravel (`pdo_pgsql`, `redis`, `intl`, `zip`) are installed and enabled.
- **Node/Nuxt**: Use `node:20-alpine`. Use `npm ci` for deterministic builds and `npm run build` in the build stage.
- **Web Server**: Use `nginx:alpine` to serve frontend assets and proxy requests to the PHP-FPM container.

### 2. Multi-Stage Build Pattern
- **Stage 1 (Builder)**: Install dependencies, run tests, and compile assets.
- **Stage 2 (Runner)**: Copy only the necessary runtime files from the Builder stage.
- **Example (Backend)**:
  ```dockerfile
  FROM composer:latest as vendor
  COPY composer.json composer.lock ./
  RUN composer install --no-dev --no-scripts --no-autoloader
  
  FROM php:8.3-fpm-alpine
  COPY --from=vendor /app/vendor /var/www/vendor
  COPY . /var/www
  ```

### 3. Local Development (Docker Compose)
- **Standard Boilerplate**: Always start with the template defined in `docker_rules.md`.
- **Fast Refresh**: Use bind mounts for the source code to enable hot reloading.
- **Service Orchestration**: Use `depends_on` with `condition: service_healthy` to ensure the database is ready before the API starts.
- **DB Management**: Use the integrated `pgadmin` service for local database exploration at `http://localhost:8080`.
- **Seeding**: Automatically run `php artisan migrate:fresh --seed` on the first startup of the dev environment.

### 4. Security Hardening
- **User Context**: Use `USER www-data` or a custom user to run the application processes.
- **Read-Only FS**: Where possible, run containers with a read-only root filesystem, using `tmpfs` for temporary directories.
- **Scanning**: Regularly scan images for vulnerabilities using tools like `trivy` or `docker scout`.

## Best Practices
- **Cache Layers**: Order your Dockerfile commands from least-frequently changed to most-frequently changed to maximize layer caching.
- **Entrypoints**: Use entrypoint scripts to handle runtime tasks like cache clearing or directory permissions.
- **Small Images**: Prefer Alpine-based images over Debian/Ubuntu to reduce attack surface and deployment time.

## Troubleshooting
- **Permission Denied**: Check the UID/GID mapping between the host and the container, especially for mounted volumes.
- **Slow Performance**: On Windows/Mac, ensure the `cached` or `delegated` flags are used for volume mounts to improve I/O speed.
- **Container Exit**: Check `docker logs <container_id>` for runtime errors or missing environment variables.
- **Networking**: If services can't talk to each other, verify they are on the same Docker network and using service names as hostnames.
