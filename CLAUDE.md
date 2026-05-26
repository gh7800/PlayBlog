# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Laravel 8.83 REST API** project (PHP ^7.4|^8.0) with modular architecture. Primary UUIDs throughout the database.

## Common Commands

```bash
# Development
php artisan serve
php artisan route:list                     # List all API routes

# Migrations (blogDb is primary)
php artisan migrate --path=database/migrations/blogDb --database=mysql
php artisan migrate:status --database=mysql
php artisan migrate:rollback --database=mysql
php artisan make:migration --path=database/migrations/blogDb

# Cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Composer
composer install
composer dump-autoload

# Frontend (Vue.js with Laravel Mix)
npm run dev
npm run watch
npm run prod

# Logs
tail -f storage/logs/laravel.log
```

## Architecture

### Directory Structure
- `app/` - Core Laravel: `Http/` (Controllers, Middleware, Requests, Resources), `Models/`, `Services/`, `Helpers/`, `Exceptions/`, `Providers/`, `Console/`
- `module/` - **Self-contained packages** with their own ServiceProvider, Controllers, Models, Services, migrations, and routes
- `plugin/car/` - Additional plugin (separate from `module/Car/`)
- `routes/` - Route files (api.php, web.php, auth.php)
- `database/migrations/` - Migrations for `blogDb`, `fileDb`, plus global migrations

### Module System
Modules in `module/` auto-register via their ServiceProvider:
- Routes loaded from `api.php` with auth:sanctum middleware
- Migrations auto-discovered from `db/` or `DB/` directories

**Modules:**
- `module/Car/` - Car management API
- `module/Document/` - Document workflow with approval flows
- `module/Notice/` - Notifications
- `module/Seal/` - Seal/Stamp management

### Database
- Primary: `blogDb` (connection: `mysql`)
- All tables use UUID primary keys
- Standard fields: `uuid`, `status` (default 0 = normal), `created_at`, `updated_at`, `deleted_at`
- Timezone: PRC | Locale: zh-cn

### Authentication
- Laravel Sanctum for API token authentication
- Protected routes use `auth:sanctum` middleware

### Key Dependencies
- `laravel/sanctum` - API auth
- `jpush/jpush` - JPush push notifications
- `phpoffice/phpword` - Word document generation
- `guzzlehttp/guzzle` - HTTP client
- `doctrine/dbal` - DBAL for migration operations
- `symfony/uid` - UUID generation
- `shineiot/framework7` - Framework7 mobile framework

## Testing
```bash
./vendor/bin/phpunit
```

## Conventions
- All queries use UUID for lookups, not auto-increment IDs
- Models use `uuid` as primary key (string type, not incrementing)
- Service layer handles business logic; controllers are thin
- Soft deletes via `deleted_at` on all tables
- Status field default 0 = normal/active
