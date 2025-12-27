# PMPulse Repository Instructions

## Project Overview

PMPulse is a single-tenant property management analytics application that ingests data from AppFolio and provides dashboards, KPIs, and email notifications. This is NOT a multi-tenant application.

## Tech Stack

- Backend: Laravel 12.x with PHP 8.3
- Frontend: React 18 + Inertia.js + Vite 6 + Tailwind CSS 4
- Database: PostgreSQL 17
- Cache/Queue: Redis 7
- Charts: Recharts
- Testing: PHPUnit

## Code Style Guidelines

### PHP/Laravel

- Use PHP 8.3 features: readonly properties, constructor property promotion, match expressions, named arguments
- Always use strict types and return type declarations
- Keep controllers thin; business logic belongs in Service classes under `app/Services/`
- Use Eloquent scopes for reusable query patterns
- Follow Laravel naming conventions (StudlyCase for classes, snake_case for database columns)
- Use dependency injection via constructor, not facades where possible
- Queue long-running operations using Laravel Jobs

### React/JavaScript

- Use functional components with hooks
- Use Inertia.js `useForm` for form handling
- Import icons from `@heroicons/react`
- Use Tailwind CSS utility classes; avoid inline styles
- Components go in `resources/js/components/`, pages in `resources/js/pages/`
- Use Recharts for data visualization

### Database

- All tables use `external_id` for AppFolio identifiers (never use AppFolio IDs as primary keys)
- Use upsert patterns with `updateOrCreate()` for idempotent data ingestion
- Index columns used in WHERE clauses and foreign keys
- Store raw API payloads in `raw_appfolio_events` for debugging

## Architecture Patterns

### Data Flow

1. `AppfolioClient` fetches data from API with rate limiting
2. `IngestionService` stores raw events and normalizes to relational tables
3. `AnalyticsService` calculates KPIs from normalized data
4. `NotificationService` evaluates alert rules and sends emails

### Key Services

- `app/Services/AppfolioClient.php` - API client with retry logic
- `app/Services/IngestionService.php` - Data normalization (has TODO markers for field mapping)
- `app/Services/AnalyticsService.php` - KPI calculations
- `app/Services/NotificationService.php` - Alert evaluation

## Testing Requirements

- Write unit tests for service classes
- Use `RefreshDatabase` trait for database tests
- Mock HTTP calls with `Http::fake()` for API tests
- Feature tests should use `actingAs()` for authenticated routes

## Security Considerations

- AppFolio credentials are encrypted in database (`client_secret_encrypted`)
- Use Laravel's `Crypt` facade for encryption/decryption
- Validate all user input in Form Request classes
- Sanitize data before database insertion

## Common Patterns

### Adding New AppFolio Resource Types

1. Add endpoint method to `AppfolioClient.php`
2. Add resource to `config/appfolio.php` resources array
3. Add upsert method in `IngestionService.php`
4. Create migration and model if new table needed

### Adding New KPIs

1. Add column to `daily_kpis` table via migration
2. Calculate value in `AnalyticsService::refreshDailyKpis()`
3. Add to dashboard React component

### Adding Alert Metrics

1. Add to `AlertRule::METRICS` constant
2. Handle in `NotificationService::getMetricValue()`
3. Add message template in `buildAlertMessage()`

## Do Not

- Do not add multi-tenancy or tenant_id fields
- Do not use AppFolio external IDs as primary keys
- Do not store credentials in plain text
- Do not skip queue for long-running sync operations
- Do not use hyphens in email notification copy
