# pet-spa-booking-saas
laravel, vue, inertia, multi-tenant, saas, booking.

## Stack
Laravel 12 · PHP 8.3 · MySQL 8 · Redis 7 · Pest · Pint · Larastan (level 6) · GitHub Actions

## Getting started (Docker)
```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```
App: http://localhost:8080

## Quality
```bash
docker compose exec app composer lint      # Pint (check)
docker compose exec app composer format    # Pint (fix)
docker compose exec app composer analyse   # Larastan level 6
docker compose exec app composer test      # Pest
docker compose exec app composer ci        # all of the above
```
CI (`.github/workflows/ci.yml`) runs Pint, Larastan and Pest against MySQL 8 + Redis.
