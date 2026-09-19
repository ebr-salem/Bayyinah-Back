> **Note:** This is a Laravel 13 API. For non-Docker local development, see [Laravel documentation](https://laravel.com/docs).

## Deployment with Docker

The repo ships with a production-ready Docker setup:

- `Dockerfile` — multi-stage build (PHP 8.4 FPM + Nginx, Composer vendor, Vite assets)
- `docker-compose.yml` — `app` (web), `queue` (worker), `db` (MySQL 8.4)
- `nginx/default.conf` — Nginx site config (served from inside the app container)
- `docker/entrypoint.sh` — role-aware startup (web / worker / one-off artisan)
- `docker/php.ini` — production PHP + Opcache settings

### 1. First-time deployment

```bash
# Configure the app. `docker compose` reads the Laravel `.env` for interpolation,
# so the values below are the ones used by the stack.
# Defaults: APP_ENV=production, APP_DEBUG=false, APP_PORT=80
export APP_ENV=production
export APP_DEBUG=false
export APP_URL=https://your-domain.com
export APP_KEY=$(php artisan key:generate --show)      # or: openssl rand -base64 32
export SUPER_ADMIN_EMAIL=superadmin@ertiqaa.com
export SUPER_ADMIN_PASSWORD=strong-password
export DB_PASSWORD=strong-db-password
export DB_ROOT_PASSWORD=strong-root-password
```

`docker compose` automatically interpolates `${VARS}` from the repository `.env`, so
you can set these values inline too. Any variable omitted falls back to the default
in `docker-compose.yml`.

### 2. Build & start

```bash
docker compose up -d --build
```

This builds the image, starts MySQL, waits for it to be healthy, then launches the
web container (bound to `APP_PORT`, default `80`) and one queue worker.
Nginx + PHP-FPM run inside the single `app` container.

### 3. First-run database setup (once)

```bash
# Apply migrations and seed the super admin + global settings
docker compose run --rm app php artisan migrate --seed --force
```

Run migrations on future deploys with `docker compose run --rm app php artisan migrate --force`.

### 4. Connect the FastAPI AI service

The app calls the AI backend at `FASTAPI_INTERNAL_URL` (default
`http://fastapi-service:8000`). The compose stack creates an attachable bridge
network named `fastapi-net`. Attach the FastAPI container to it once:

```bash
docker network connect fastapi-net <fastapi-container>
```

Both containers then resolve each other by service/container name on that network.

### 5. Day-to-day operations

```bash
docker compose ps                # status
docker compose logs -f app       # app logs (nginx + php-fpm on stdout)
docker compose logs queue        # queue worker logs
docker compose up -d --build     # rebuild & redeploy after a code change
docker compose exec app php artisan tinker
docker compose exec app php artisan queue:retry all
docker compose down              # stop (data in the db_data volume persists)
```

### Notable production settings

- `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database` — no Redis required.
- Config/routes/views are cached on boot when `APP_ENV=production`.
- OPCache is enabled with `validate_timestamps=0` in production images.
- Uploads limited to 25 MB (`client_max_body_size` + `upload_max_filesize`).
- The queue worker survives via `restart: unless-stopped` and re-tries jobs 3x with a 90s timeout.

## About Laravel

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
