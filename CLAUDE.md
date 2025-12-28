# CLAUDE.md

This file provides context and instructions for Claude Code when working on the PMPulse project.

## Project Overview

PMPulse is a single-tenant property management analytics application that ingests data from AppFolio and provides dashboards, KPIs, and email notifications. It is built for a single property management company (no multitenancy).

## Tech Stack

- **Backend**: Laravel 12.x, PHP 8.3
- **Frontend**: React 18 + Inertia.js + Vite 6 + Tailwind CSS 4
- **Database**: PostgreSQL 17
- **Cache/Queue**: Redis 7
- **Charts**: Recharts
- **Testing**: PHPUnit
- **CI**: GitHub Actions

## Project Structure

```
app/
├── Console/Commands/          # Artisan commands (appfolio:sync, analytics:refresh, alerts:evaluate)
├── Http/Controllers/          # Web and API controllers
│   ├── Api/                   # API endpoints (health, dashboard, sync)
│   └── Auth/                  # Authentication
├── Jobs/                      # Queue jobs (SyncAppfolioResourceJob, RefreshAnalyticsJob)
├── Models/                    # Eloquent models
├── Notifications/             # Email notifications
└── Services/                  # Business logic
    ├── AppfolioClient.php     # AppFolio API client with rate limiting
    ├── IngestionService.php   # Data normalization and upsert logic
    ├── AnalyticsService.php   # KPI calculations
    └── NotificationService.php # Alert evaluation and sending

resources/js/
├── components/                # Reusable React components
│   ├── Dashboard/             # Dashboard widgets (KpiCard, SyncStatus, charts)
│   └── Admin/                 # Admin components (ConnectionForm, SyncHistory)
├── pages/                     # Inertia page components
│   ├── Auth/Login.jsx
│   ├── Dashboard.jsx
│   └── Admin.jsx
└── app.jsx                    # Application entry point
```

## Key Files to Know

- `app/Services/AppfolioClient.php` - API client with TODO markers for actual AppFolio endpoints
- `app/Services/IngestionService.php` - Data mapping with TODO markers for field names
- `config/appfolio.php` - AppFolio configuration (rate limits, sync settings)
- `config/features.php` - Feature flags
- `routes/console.php` - Scheduled tasks

## Development Commands

```bash
# Start Docker environment
docker compose up -d

# Run inside containers
docker compose exec app composer install
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
docker compose exec node npm install
docker compose exec node npm run dev  # For HMR

# Run tests
docker compose exec app php artisan test

# Lint code
docker compose exec app ./vendor/bin/pint

# Manual sync
docker compose exec app php artisan appfolio:sync --mode=incremental
docker compose exec app php artisan appfolio:sync --mode=full

# Refresh analytics
docker compose exec app php artisan analytics:refresh --sync

# Evaluate alerts
docker compose exec app php artisan alerts:evaluate
```

## Local Ports (Non-standard to avoid conflicts)

| Service | Port |
|---------|------|
| Web Application | 8180 |
| PostgreSQL | 5433 |
| Redis | 6380 |
| Vite Dev Server | 5174 |
| MailHog SMTP | 1026 |
| MailHog Web UI | 8126 |

## Database Schema

**Core Tables:**
- `appfolio_connections` - API credentials (client_secret is encrypted)
- `sync_runs` - Tracks each sync job with status, counts, errors
- `raw_appfolio_events` - Raw JSON payloads for debugging/replay

**Normalized Tables:**
- `properties` - Property records with address fields
- `units` - Units linked to properties (status: vacant/occupied/not_ready)
- `people` - Tenants, owners, vendors
- `leases` - Lease records linking units to people
- `ledger_transactions` - Financial transactions
- `work_orders` - Maintenance requests

**Analytics Tables:**
- `daily_kpis` - Portfolio-level daily metrics
- `property_rollups` - Per-property daily metrics

**Configuration Tables:**
- `alert_rules` - Notification thresholds
- `feature_flags` - Feature toggles

## AppFolio Integration Status

The AppFolio client is implemented with **placeholder endpoints**. When actual API documentation is provided:

1. Update endpoint paths in `AppfolioClient.php` (marked with TODO)
2. Update field mappings in `IngestionService.php` (marked with TODO)
3. Adjust authentication method if needed (currently uses Basic Auth)

## Testing Guidelines

- Unit tests are in `tests/Unit/`
- Feature tests are in `tests/Feature/`
- Use `RefreshDatabase` trait for database tests
- Mock HTTP calls with `Http::fake()` for API client tests

## Code Style

- Follow Laravel conventions
- Use PHP 8.3 features (readonly, constructor promotion, match expressions)
- Use typed properties and return types
- Keep controllers thin, business logic in Services
- Use Eloquent scopes for common query patterns

## Common Tasks

### Adding a new resource type to sync
1. Add endpoint to `AppfolioClient.php`
2. Add resource to `config/appfolio.php` resources array
3. Add upsert method in `IngestionService.php`
4. Create model and migration if new table needed

### Adding a new alert metric
1. Add metric to `AlertRule::METRICS` constant
2. Add case to `getMetricValue()` in `NotificationService.php`
3. Add message template in `buildAlertMessage()`

### Adding a new KPI
1. Add column to `daily_kpis` migration
2. Calculate in `AnalyticsService::refreshDailyKpis()`
3. Display in Dashboard React component

## Environment Variables

Key variables to configure:
- `APPFOLIO_CLIENT_ID`, `APPFOLIO_CLIENT_SECRET` - API credentials
- `APPFOLIO_FULL_SYNC_TIME` - When to run full sync (default: 02:00)
- `FEATURE_INCREMENTAL_SYNC` - Enable/disable incremental sync
- `FEATURE_NOTIFICATIONS` - Enable/disable email alerts

## Troubleshooting

### Containers not starting
```bash
docker compose logs app
docker compose logs postgres
```

### Queue jobs not processing
```bash
docker compose logs queue
docker compose exec app php artisan queue:restart
```

### Migrations failing
```bash
docker compose exec app php artisan migrate:status
docker compose exec app php artisan migrate:fresh --seed  # WARNING: destroys data
```

### Frontend not updating
```bash
docker compose exec node npm run build
# Or for dev with HMR:
docker compose exec node npm run dev
```

### Linear Workflow

**Project Management Principles:**
- Linear is the project management system - break work into small, digestible tasks
- Each issue should represent 1-2 hours of focused work maximum
- **NEVER** create monolithic issues covering entire phases or features

**Issue Creation Rules:**
1. **Use existing labels** - Check available labels before creating issues. NEVER create new labels without explicit approval
2. **Small scope** - One migration, one model, one controller method, one component per issue
3. **Clear titles** - Action-oriented: "Create brands table migration", "Add LocationController index endpoint"
4. **Preview first** - When planning larger features, preview the issue breakdown in chat before creating in Linear

**Workflow:**
- Always use Linear MCP tools to check for issue details and status
- When starting work: Update status to **In Progress**
- When completed: Update status to **In Review** with a comment describing what was completed
- Link PRs to issues when creating pull requests

**Branch Naming:**
- `feature/issue-name` - New features
- `fix/issue-name` - Bug fixes
- `patch/description` - Small improvements/cleanup
- `feature/phase-description` - Multi-issue feature work
