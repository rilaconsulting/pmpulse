# PMPulse Code Style Guide

## Project Overview

PMPulse is a single-tenant property management analytics application that ingests data from AppFolio and provides dashboards, KPIs, and email notifications. This is NOT a multi-tenant application.

## Tech Stack

- Backend: Laravel 12.x with PHP 8.3
- Frontend: React 18 + Inertia.js + Vite 6 + Tailwind CSS 4
- Database: PostgreSQL 17
- Cache/Queue: Redis 7
- Charts: Recharts
- Testing: PHPUnit

## PHP/Laravel Standards

### Language Features

- Use PHP 8.3 features: readonly properties, constructor property promotion, match expressions, named arguments
- Always declare strict types at the top of files: `declare(strict_types=1);`
- Always include return type declarations on all methods

### Architecture

- Keep controllers thin; business logic belongs in Service classes under `app/Services/`
- Use Eloquent scopes for reusable query patterns
- Use dependency injection via constructor, not facades where possible
- Queue long-running operations using Laravel Jobs

### Naming Conventions

- Classes: StudlyCase (e.g., `IngestionService`, `AppfolioClient`)
- Methods: camelCase (e.g., `getProperties`, `refreshDailyKpis`)
- Database columns: snake_case (e.g., `external_id`, `created_at`)
- Constants: SCREAMING_SNAKE_CASE (e.g., `METRICS`, `MAX_RETRIES`)

### Database Patterns

- All tables use `external_id` for AppFolio identifiers (never use AppFolio IDs as primary keys)
- Use upsert patterns with `updateOrCreate()` for idempotent data ingestion
- Index columns used in WHERE clauses and foreign keys
- Store raw API payloads in `raw_appfolio_events` for debugging

## React/JavaScript Standards

### Components

- Use functional components with hooks exclusively
- Use Inertia.js `useForm` for form handling
- Import icons from `@heroicons/react`
- Components go in `resources/js/components/`, pages in `resources/js/pages/`

### Styling

- Use Tailwind CSS utility classes; avoid inline styles
- Never use custom CSS when Tailwind utilities suffice

### Data Visualization

- Use Recharts for all charts and graphs

## Security Requirements

- AppFolio credentials must be encrypted in database using Laravel's `Crypt` facade
- Validate all user input in Form Request classes
- Sanitize data before database insertion
- Never log or expose sensitive credentials

## Testing Requirements

- Write unit tests for service classes
- Use `RefreshDatabase` trait for database tests
- Mock HTTP calls with `Http::fake()` for API tests
- Feature tests should use `actingAs()` for authenticated routes

## Code Review Focus Areas

When reviewing code, pay special attention to:

1. **No Multi-tenancy**: Reject any code that adds tenant_id fields or multi-tenant patterns
2. **External ID Usage**: Ensure AppFolio IDs are stored in `external_id` columns, never as primary keys
3. **Credential Security**: Verify credentials are encrypted, never stored in plain text
4. **Queue Usage**: Long-running sync operations must use Laravel Jobs
5. **Email Copy**: Notification email text must not contain hyphens
6. **Idempotent Operations**: Data ingestion must use `updateOrCreate()` patterns

## Prohibited Patterns

- Do not add multi-tenancy or tenant_id fields
- Do not use AppFolio external IDs as primary keys
- Do not store credentials in plain text
- Do not skip queue for long-running sync operations
- Do not use hyphens in email notification copy
- Do not use facades when dependency injection is possible
