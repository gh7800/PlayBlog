# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **Laravel 7.x REST API** project (PHP 7.2.5+/8.0+) with modular architecture. Uses Composer for PHP dependencies and npm for frontend assets.

## Common Commands

```bash
# Development server
php artisan serve

# Migrations (blogDb is the primary database)
php artisan migrate --path=database/migrations/blogDb --database=mysql
php artisan migrate:status
php artisan migrate:rollback

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Composer
composer install
composer update
composer dump-autoload

# Frontend (Vue.js with Laravel Mix)
npm run dev        # Development build
npm run watch      # Watch mode
npm run prod       # Production build
```

## Architecture

### Directory Structure
- `app/` - Core Laravel application (Controllers, Middleware, Providers, Services)
- `module/` - **Custom modular packages** - each is self-contained with Controllers, Models, Services, Repository, migrations, and routes
- `plugin/` - Additional plugins (e.g., `car/`)
- `config/` - Laravel configuration files
- `routes/` - Route files (api.php, web.php, auth.php, etc.)
- `database/migrations/blogDb/` - Blog database migrations
- `database/migrations/fileDb/` - File database migrations (configured but unused)

### Custom Module System
Modules in `module/` are self-registering packages. Each module provides its own:
- ServiceProvider that auto-loads routes from `api.php` and migrations from `db/` or `DB/`
- Routes prefixed in `routes/api.php` (e.g., `api/document`, `api/car`)
- Authentication via `auth:sanctum` middleware

**Known modules:**
- `module/Document/` - Document workflow with approval flows (DocumentService.php)
- `module/Car/` - Car management API

### Database
- Primary database: `blogDb`
- Secondary database: `fileDb` (configured but not actively used)
- Timezone: PRC | Locale: zh-cn

### Authentication
- Laravel Sanctum for API token authentication
- Protected routes use `auth:sanctum` middleware

### Key Dependencies
- `jpush/jpush` - JPush notification service
- `shineiot/framework7` - Framework7 mobile app framework
- `guzzlehttp/guzzle` - HTTP client

## Testing
- PHPUnit configuration in `phpunit.xml`
- Tests in `tests/Feature/` and `tests/Unit/`
