# CLAUDE.md

This file provides context and instructions for Claude Code when working on the PMPulse project.

## Project Overview

PMPulse is a single-tenant property management analytics application that ingests data from AppFolio and provides dashboards, KPIs, and email notifications. It is built for a single property management company (no multitenancy).

## Tech Stack

- **Backend**: Laravel 12.x, PHP 8.3
- **Frontend**: React 18 + Inertia.js + Vite 6 + Tailwind CSS 4
- **Database**: PostgreSQL 17 (also used for sessions, cache, queue)
- **Charts**: Recharts
- **Testing**: PHPUnit
- **CI**: GitHub Actions
- **Production**: Laravel Cloud (Staging + Production environments)

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

## Local Development Architecture

The local development environment uses Docker with 4 containers:
- **app**: PHP-FPM + Supervisor (manages queue worker and scheduler)
- **nginx**: Web server
- **postgres**: Database (also handles sessions, cache, queue)
- **node**: Vite dev server for frontend HMR

## Local Ports (Non-standard to avoid conflicts)

| Service | Port |
|---------|------|
| Web Application | 8180 |
| PostgreSQL | 5433 |
| Vite Dev Server | 5174 |

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
- `settings` - Unified key/value store for all application configuration (organized by category)
- `alert_rules` - Notification thresholds
- `sync_failure_alerts` - Tracks consecutive sync failures for alerting

**Setting Categories:**
- `sync` - Sync timing settings (full_sync_time)
- `business_hours` - Business hours configuration (enabled, timezone, start_hour, end_hour, etc.)
- `features` - Feature flags (incremental_sync, notifications)
- `appfolio` - AppFolio API credentials (encrypted)

## AppFolio Reports API Reference

The AppFolio Reports API specification is available in `appfolio-reports-openapi-spec.json` in the project root.

### Key Endpoints Used

| Endpoint | Purpose |
|----------|---------|
| `/property_directory.json` | Property details (sqft, unit counts, portfolio, year built) |
| `/unit_directory.json` | Unit details (sqft, bedrooms, bathrooms, market rent) |
| `/vendor_directory.json` | Vendor profiles with insurance expiration dates |
| `/work_order.json` | Work orders with vendor, costs, status |
| `/expense_register.json` | Expenses including utilities |
| `/rent_roll.json` | Current lease and rent information |
| `/delinquency.json` | Delinquent accounts |

### API Notes

- All endpoints use POST method with JSON request body
- Responses are paginated with `next_page_url` field
- Dates are returned as strings (parse with Carbon)
- Amounts are returned as strings (parse to decimal)
- IDs are integers (`property_id`, `unit_id`, `vendor_id`)

### Response Pagination

```php
// Handle paginated responses
do {
    $response = $this->request('POST', $endpoint, $params);
    $results = array_merge($results, $response['results']);
    $nextUrl = $response['next_page_url'] ?? null;
} while ($nextUrl);
```

### Common Field Mappings

**Property Directory:**
- `property_id` → `external_id`
- `property_name` → `name`
- `property_street`, `property_city`, `property_state`, `property_zip` → address fields
- `sqft` → `total_sqft`
- `units` → `unit_count`

**Vendor Directory:**
- `vendor_id` → `external_id`
- `company_name` → `company_name`
- `workers_comp_expires` → `workers_comp_expires` (parse date)
- `liability_ins_expires` → `liability_ins_expires` (parse date)
- `do_not_use_for_work_order` → `do_not_use`

**Work Order:**
- `work_order_id` → `external_id`
- `vendor_id` → link to vendor
- `amount` → `amount` (parse to decimal)
- `created_at` → `opened_at`
- `completed_on` → `closed_at`

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

### UUID Primary Keys

All models use UUID primary keys via Laravel's `HasUuids` trait:

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Property extends Model
{
    use HasFactory, HasUuids;
}
```

Migrations use `uuid()` for primary keys and `foreignUuid()` for relationships:

```php
$table->uuid('id')->primary();
$table->foreignUuid('property_id')->constrained()->cascadeOnDelete();
```

### Setting Model Usage

Use the `Setting` model for all application configuration:

```php
use App\Models\Setting;

// Reading settings
$value = Setting::get('category', 'key', 'default');

// Writing settings
Setting::set('category', 'key', $value);

// Encrypted settings (e.g., API secrets)
Setting::set('appfolio', 'client_secret', $secret, encrypted: true);

// Get all settings in a category
$syncSettings = Setting::getCategory('sync');

// Check feature flags
if (Setting::isFeatureEnabled('notifications', default: true)) {
    // ...
}
```

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

## Deployment

### Environments
- **Local**: Docker Compose (4 containers)
- **Staging**: Laravel Cloud (`APP_ENV=staging`)
- **Production**: Laravel Cloud (`APP_ENV=production`)

### Laravel Cloud Overview

Laravel Cloud is the official infrastructure platform for Laravel. Key features:
- **Zero-config deployments**: No YAML files or CLI tools required
- **Push-to-deploy**: Automatic deployments on git push (enabled by default)
- **Zero-downtime**: Graceful termination of existing processes
- **Auto-scaling**: Scales based on traffic and job queue depth
- **Hibernation**: Auto-hibernates inactive staging environments to save costs

**Requirements:**
- Laravel 9+ (we use Laravel 12.x)
- Latest minor version of `laravel/framework`
- PHP 8.2+ (Cloud supports 8.2, 8.3, 8.4, 8.5)

### Laravel Cloud Configuration

All configuration is done via the Laravel Cloud dashboard - no config files in the repository.

**Build Commands** (run during deployment, 15-min timeout):
```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
LARAVEL_CLOUD=1 php artisan config:cache
```

**Deploy Commands** (run before going live, 15-min timeout):
```bash
php artisan migrate --force
```

**Commands to AVOID in deploy hooks:**
- `php artisan queue:restart` (automatic)
- `php artisan optimize:clear` (breaks caching)
- `php artisan storage:link` (ephemeral filesystem)

### Queue Workers on Laravel Cloud

Three options for queue processing:

1. **Queue Clusters** (Recommended for production)
   - Dedicated infrastructure isolated from web traffic
   - Auto-scales based on job latency and queue depth
   - Create via: Infrastructure Canvas → "New queue cluster"

2. **Worker Clusters**
   - Dedicated clusters for custom workers (e.g., Laravel Horizon)
   - Separate from app compute

3. **App Cluster Background Processes** (Good for staging)
   - Runs on same compute as web traffic
   - Configure in: App Cluster → Background Processes → New

No `queue:restart` needed after deployments - Laravel Cloud handles this automatically.

### Scheduler on Laravel Cloud

Enable the scheduler in the Laravel Cloud dashboard:
1. Click on environment's App compute cluster
2. Enable the "Scheduler" toggle
3. Save and redeploy

The `schedule:run` command runs every minute automatically.

**Important for multi-replica:** Use `->onOneServer()` on scheduled tasks to prevent duplicate execution:
```php
$schedule->command('appfolio:sync --mode=incremental')
    ->everyFifteenMinutes()
    ->onOneServer();
```

### Database on Laravel Cloud

Laravel Cloud offers:
- **Serverless PostgreSQL**: Auto-scales with demand (recommended)
- **MySQL**: Traditional managed instances
- **Bring your own**: Configure external database credentials

Cloud automatically injects `DB_*` environment variables when you attach a database.

### Environment Variables

Custom environment variables are set via the dashboard. Cloud auto-injects:
- Database credentials (`DB_HOST`, `DB_DATABASE`, etc.)
- Cache/Redis credentials (if using managed cache)
- Object storage credentials (if using managed storage)

Custom variables override auto-injected ones.

### Environment-Specific Configuration

**Local Development:**
```env
APP_ENV=local
APP_DEBUG=true
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
MAIL_MAILER=log
```

**Staging (Laravel Cloud):**
```env
APP_ENV=staging
APP_DEBUG=false
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
MAIL_MAILER=log  # Or configure test mail service
```
- Enable hibernation to reduce costs
- Use separate database from production
- Consider unique `CACHE_PREFIX` if sharing cache

**Production (Laravel Cloud):**
```env
APP_ENV=production
APP_DEBUG=false
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
MAIL_MAILER=ses  # Or mailgun, postmark
```

### Deployment Workflow

1. **Push to GitHub** → Triggers automatic deployment
2. **Build phase**: Installs dependencies, compiles assets
3. **Deploy phase**: Runs migrations
4. **Rollout**: Zero-downtime swap to new deployment

**Deploy Hooks** (for CI/CD integration):
```yaml
# .github/workflows/deploy.yml
name: Deploy to Laravel Cloud
on:
  push:
    branches:
      - main      # Triggers production deployment
      - develop   # Triggers staging deployment
jobs:
  deploy:
    runs-on: ubuntu-latest
    environment:
      name: ${{ github.ref == 'refs/heads/main' && 'production' || 'staging' }}
    steps:
      - name: Trigger Deploy
        run: |
          if [ "${{ github.ref }}" == "refs/heads/main" ]; then
            curl -X POST "${{ secrets.LARAVEL_CLOUD_DEPLOY_HOOK_PRODUCTION }}"
          else
            curl -X POST "${{ secrets.LARAVEL_CLOUD_DEPLOY_HOOK_STAGING }}"
          fi
```

### Important Notes

- **Ephemeral filesystem**: Files don't persist across deployments. Use Object Storage for uploads.
- **No Redis required**: We use PostgreSQL for sessions, cache, and queue.
- **Hibernation**: Staging environments auto-sleep when inactive; wake on first request.

## Troubleshooting

### Containers not starting
```bash
docker compose logs app
docker compose logs postgres
```

### Queue jobs not processing
Queue worker runs via Supervisor in the app container:
```bash
docker compose logs app | grep -i queue
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
