# PMPulse Build Plan

## Overview
Single-tenant property management analytics application that ingests AppFolio data and provides insights, dashboards, and email notifications.

## Technology Stack
- **Backend**: Laravel 12.x, PHP 8.4.x (latest stable - 8.5 not released yet)
- **Frontend**: React 18.x + Vite 6.x + Tailwind CSS 4.x
- **Database**: PostgreSQL 17.x (latest stable - 18 not released yet)
- **Cache/Queue**: Redis 7.x (latest stable - 8.4 not released yet)
- **Node**: 22.x LTS
- **Auth**: Laravel built-in session auth + API tokens
- **Email**: Laravel Notifications (SMTP)
- **Dev/Deploy**: Docker Compose

## Directory Structure

```
pmpulse/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── AppfolioSyncCommand.php
│   │       └── AnalyticsRefreshCommand.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   └── HealthController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── AdminController.php
│   │   │   └── Auth/
│   │   └── Middleware/
│   ├── Jobs/
│   │   ├── SyncAppfolioResourceJob.php
│   │   ├── ProcessRawAppfolioEventJob.php
│   │   └── RefreshAnalyticsJob.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── AppfolioConnection.php
│   │   ├── SyncRun.php
│   │   ├── RawAppfolioEvent.php
│   │   ├── Property.php
│   │   ├── Unit.php
│   │   ├── Person.php
│   │   ├── Lease.php
│   │   ├── LedgerTransaction.php
│   │   ├── WorkOrder.php
│   │   ├── DailyKpi.php
│   │   ├── PropertyRollup.php
│   │   ├── AlertRule.php
│   │   └── FeatureFlag.php
│   ├── Notifications/
│   │   └── AlertNotification.php
│   └── Services/
│       ├── AppfolioClient.php
│       ├── IngestionService.php
│       ├── AnalyticsService.php
│       └── NotificationService.php
├── config/
│   ├── appfolio.php
│   └── features.php
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── docker/
│   ├── nginx/
│   │   └── default.conf
│   ├── php/
│   │   └── Dockerfile
│   └── scheduler/
│       └── crontab
├── resources/
│   ├── js/
│   │   ├── app.jsx
│   │   ├── components/
│   │   │   ├── Layout.jsx
│   │   │   ├── Dashboard/
│   │   │   │   ├── KpiCard.jsx
│   │   │   │   ├── SyncStatus.jsx
│   │   │   │   └── Charts/
│   │   │   └── Admin/
│   │   │       ├── ConnectionForm.jsx
│   │   │       └── SyncHistory.jsx
│   │   └── pages/
│   │       ├── Login.jsx
│   │       ├── Dashboard.jsx
│   │       └── Admin.jsx
│   ├── css/
│   │   └── app.css
│   └── views/
│       └── app.blade.php
├── routes/
│   ├── web.php
│   └── api.php
├── tests/
│   ├── Unit/
│   │   ├── AppfolioClientTest.php
│   │   ├── IngestionServiceTest.php
│   │   └── AnalyticsServiceTest.php
│   └── Feature/
│       └── DashboardTest.php
├── .env.example
├── .github/
│   └── workflows/
│       └── ci.yml
├── docker-compose.yml
├── docker-compose.prod.yml
├── vite.config.js
├── tailwind.config.js
├── package.json
└── README.md
```

## Build Phases

### Phase 1: Project Scaffold
1. Initialize Laravel 12.x project
2. Configure authentication (Breeze with React)
3. Set up Vite + Tailwind CSS
4. Create base React components

### Phase 2: Docker Environment
1. Create PHP-FPM Dockerfile
2. Configure Nginx
3. Set up PostgreSQL service
4. Set up Redis service
5. Create docker-compose.yml for local dev
6. Add scheduler container configuration

### Phase 3: Database Schema
1. Create all migrations
2. Define Eloquent models with relationships
3. Add indexes for performance
4. Create seeders for test data

### Phase 4: AppFolio Integration
1. Build AppfolioClient service
   - HTTP client with retry logic
   - Rate limiting (exponential backoff)
   - Credential management
2. Create IngestionService
   - Raw payload storage
   - Normalization logic
   - Upsert patterns
3. Implement sync jobs
   - Full sync job
   - Incremental sync job
4. Add console commands

### Phase 5: Analytics
1. Create analytics tables
2. Build AnalyticsService
3. Implement nightly refresh job
4. Add rollup calculations

### Phase 6: Web UI
1. Build layout component
2. Create login page
3. Build dashboard with:
   - Sync status widget
   - KPI cards
   - Charts (using Chart.js or Recharts)
4. Build admin page with:
   - Connection configuration form
   - Manual sync trigger
   - Sync history table

### Phase 7: Notifications
1. Define alert rules schema
2. Build NotificationService
3. Create email notification
4. Implement daily alert evaluation job

### Phase 8: Testing & CI
1. Write unit tests
2. Write feature tests
3. Set up GitHub Actions workflow
4. Add code quality checks

### Phase 9: Documentation
1. Complete README
2. Document environment variables
3. Add deployment instructions

## Database Schema

### Core Tables

```sql
-- users (Laravel default + additions)
users (
    id, name, email, email_verified_at, password,
    api_token, remember_token, created_at, updated_at
)

-- AppFolio connection settings
appfolio_connections (
    id, name, client_id, client_secret_encrypted,
    api_base_url, status, last_success_at, last_error,
    sync_config, created_at, updated_at
)

-- Sync run tracking
sync_runs (
    id, appfolio_connection_id, mode, status,
    started_at, ended_at, resources_synced, errors_count,
    error_summary, metadata, created_at, updated_at
)

-- Raw API payloads
raw_appfolio_events (
    id, sync_run_id, resource_type, external_id,
    payload_json, pulled_at, processed_at, created_at
)
```

### Normalized Tables

```sql
properties (id, external_id, name, address_line1, address_line2, city, state, zip, created_at, updated_at)
units (id, external_id, property_id, unit_number, sqft, bedrooms, bathrooms, status, created_at, updated_at)
people (id, external_id, name, email, phone, type, created_at, updated_at)
leases (id, external_id, unit_id, person_id, start_date, end_date, rent, status, created_at, updated_at)
ledger_transactions (id, external_id, property_id, unit_id, date, type, amount, category, description, created_at, updated_at)
work_orders (id, external_id, property_id, unit_id, opened_at, closed_at, status, priority, category, description, created_at, updated_at)
```

### Analytics Tables

```sql
daily_kpis (id, date, occupancy_rate, vacancy_count, delinquency_amount, open_work_orders, avg_days_open_work_orders, created_at, updated_at)
property_rollups (id, date, property_id, vacancy_count, delinquency_amount, open_work_orders, created_at, updated_at)
```

### Configuration Tables

```sql
alert_rules (id, name, metric, operator, threshold, enabled, recipients, created_at, updated_at)
feature_flags (id, name, enabled, created_at, updated_at)
```

## Environment Variables

```env
# Application
APP_NAME=PMPulse
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=pmpulse
DB_USERNAME=pmpulse
DB_PASSWORD=secret

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=redis

# AppFolio
APPFOLIO_CLIENT_ID=
APPFOLIO_CLIENT_SECRET=
APPFOLIO_API_BASE_URL=https://api.appfolio.com

# Sync Configuration
APPFOLIO_FULL_SYNC_TIME=02:00
APPFOLIO_INCREMENTAL_SYNC_INTERVAL=15

# Features
FEATURE_INCREMENTAL_SYNC=true
FEATURE_NOTIFICATIONS=true

# Mail
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=noreply@pmpulse.local
MAIL_FROM_NAME="${APP_NAME}"
```

## API Endpoints

### Web Routes
- `GET /` - Redirect to dashboard
- `GET /login` - Login page
- `POST /login` - Authenticate
- `POST /logout` - Logout
- `GET /dashboard` - Main dashboard
- `GET /admin` - Admin settings
- `POST /admin/connection` - Save AppFolio connection
- `POST /admin/sync` - Trigger manual sync

### API Routes
- `GET /api/health` - Health check endpoint
- `GET /api/dashboard/stats` - Dashboard statistics
- `GET /api/sync/history` - Sync run history
- `POST /api/sync/trigger` - Trigger sync via API

## Next Steps
After reviewing this plan, we will begin implementation starting with Phase 1.
