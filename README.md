# PMPulse - Property Management Analytics

PMPulse is a property management analytics dashboard that ingests data from AppFolio and provides insights, KPIs, and automated email notifications.

## Features

- **AppFolio Data Ingestion**: Automated sync with rate limiting, exponential backoff, and idempotent upserts
- **Analytics Dashboard**: Real-time KPIs including occupancy rates, delinquency, and work order aging
- **Property Rollups**: Per-property metrics and comparisons
- **Email Notifications**: Configurable alerts when thresholds are exceeded
- **Admin Panel**: Configure connections, trigger manual syncs, view sync history

## Tech Stack

- **Backend**: Laravel 12.x, PHP 8.3
- **Frontend**: React 18, Vite 6, Tailwind CSS 4
- **Database**: PostgreSQL 17
- **Cache/Queue**: Redis 7
- **Charts**: Recharts

## Requirements

- Docker and Docker Compose
- Node.js 22 LTS (for local development)
- Git

## Quick Start

### 1. Clone the repository

```bash
git clone https://github.com/your-org/pmpulse.git
cd pmpulse
```

### 2. Copy environment file

```bash
cp .env.example .env
```

### 3. Start Docker services

```bash
docker compose up -d
```

### 4. Install dependencies and set up the application

```bash
# Install PHP dependencies
docker compose exec app composer install

# Generate application key
docker compose exec app php artisan key:generate

# Run migrations
docker compose exec app php artisan migrate

# Seed the database (creates admin user)
docker compose exec app php artisan db:seed

# Install Node dependencies and build assets
docker compose exec node npm install
docker compose exec node npm run build
```

### 5. Access the application

- **Application**: http://localhost:8180
- **MailHog (email testing)**: http://localhost:8126

### Local Ports

| Service | Port |
|---------|------|
| Web Application | 8180 |
| PostgreSQL | 5433 |
| Redis | 6380 |
| Vite Dev Server | 5174 |
| MailHog SMTP | 1026 |
| MailHog Web UI | 8126 |

### Default Login

- **Email**: admin@pmpulse.local
- **Password**: password

## Development

### Running the development server

For hot module replacement during development:

```bash
docker compose exec node npm run dev
```

### Running tests

```bash
docker compose exec app php artisan test
```

### Running code linting

```bash
docker compose exec app ./vendor/bin/pint
```

## Architecture

### Directory Structure

```
pmpulse/
├── app/
│   ├── Console/Commands/     # Artisan commands
│   ├── Http/Controllers/     # Web and API controllers
│   ├── Jobs/                 # Queue jobs
│   ├── Models/               # Eloquent models
│   ├── Notifications/        # Email notifications
│   └── Services/             # Business logic
│       ├── AppfolioClient.php      # API client
│       ├── IngestionService.php    # Data normalization
│       ├── AnalyticsService.php    # KPI calculations
│       └── NotificationService.php # Alert evaluation
├── database/
│   ├── migrations/           # Database schema
│   └── seeders/              # Initial data
├── docker/                   # Docker configuration
├── resources/
│   └── js/                   # React frontend
│       ├── components/       # Reusable components
│       └── pages/            # Page components
└── tests/                    # PHPUnit tests
```

### Data Flow

1. **Sync Job** fetches data from AppFolio API
2. **Raw events** are stored for debugging/replay
3. **Ingestion Service** normalizes and upserts to relational tables
4. **Analytics Service** calculates daily KPIs and property rollups
5. **Notification Service** evaluates alert rules and sends emails

### Database Schema

**Core Tables:**
- `appfolio_connections` - API credentials and connection status
- `sync_runs` - Sync job history and metrics
- `raw_appfolio_events` - Raw API payloads

**Normalized Tables:**
- `properties`, `units`, `people`, `leases`
- `ledger_transactions`, `work_orders`

**Analytics Tables:**
- `daily_kpis` - Portfolio-level daily metrics
- `property_rollups` - Property-level daily metrics

**Configuration Tables:**
- `alert_rules` - Notification thresholds
- `feature_flags` - Feature toggles

## Configuration

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APPFOLIO_CLIENT_ID` | AppFolio API client ID | |
| `APPFOLIO_CLIENT_SECRET` | AppFolio API client secret | |
| `APPFOLIO_API_BASE_URL` | AppFolio API base URL | https://api.appfolio.com |
| `APPFOLIO_FULL_SYNC_TIME` | Time for daily full sync | 02:00 |
| `APPFOLIO_INCREMENTAL_SYNC_INTERVAL` | Minutes between incremental syncs | 15 |
| `FEATURE_INCREMENTAL_SYNC` | Enable incremental sync | true |
| `FEATURE_NOTIFICATIONS` | Enable email notifications | true |

### Scheduled Tasks

The following tasks run automatically:

- **Full Sync**: Daily at 2:00 AM (configurable)
- **Incremental Sync**: Every 15 minutes (configurable)
- **Analytics Refresh**: Daily at 3:00 AM
- **Alert Evaluation**: Daily at 8:00 AM

### Console Commands

```bash
# Run a sync manually
php artisan appfolio:sync --mode=incremental
php artisan appfolio:sync --mode=full

# Refresh analytics
php artisan analytics:refresh
php artisan analytics:refresh --date=2024-01-15 --sync

# Evaluate alerts
php artisan alerts:evaluate
```

## Production Deployment

### Using Docker Compose

1. Copy production compose file:
```bash
cp docker-compose.prod.yml docker-compose.override.yml
```

2. Set production environment variables in `.env`

3. Build and deploy:
```bash
docker compose build
docker compose up -d

# Run migrations
docker compose exec app php artisan migrate --force

# Build assets
docker compose exec app npm run build
```

### Required Environment Variables for Production

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...  # Generate with: php artisan key:generate

DB_HOST=postgres
DB_DATABASE=pmpulse
DB_USERNAME=pmpulse
DB_PASSWORD=<strong-password>

REDIS_HOST=redis

MAIL_HOST=<smtp-host>
MAIL_PORT=587
MAIL_USERNAME=<smtp-user>
MAIL_PASSWORD=<smtp-password>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com

APPFOLIO_CLIENT_ID=<client-id>
APPFOLIO_CLIENT_SECRET=<client-secret>
```

## API Endpoints

### Health Check
```
GET /api/health
```
Returns service status (database, Redis, AppFolio connection, last sync).

### Dashboard Stats (Authenticated)
```
GET /api/dashboard/stats?days=30
```
Returns current KPIs and trend data.

### Sync History (Authenticated)
```
GET /api/sync/history?limit=20
```
Returns recent sync runs.

### Trigger Sync (Authenticated)
```
POST /api/sync/trigger
Body: { "mode": "incremental" | "full" }
```
Queues a sync job.

## AppFolio Integration

The AppFolio client is implemented with placeholder endpoints. When connecting to the actual AppFolio API:

1. Update endpoint paths in `app/Services/AppfolioClient.php`
2. Update field mappings in `app/Services/IngestionService.php`
3. Adjust authentication method if needed

All TODO comments in these files indicate where changes are needed.

## Testing

```bash
# Run all tests
php artisan test

# Run specific test suites
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Run with coverage
php artisan test --coverage
```

## Troubleshooting

### Sync not running
1. Check queue worker is running: `docker compose logs queue`
2. Check scheduler is running: `docker compose logs scheduler`
3. Verify AppFolio credentials in Admin panel

### Missing data
1. Check sync history for errors
2. Review raw events: `SELECT * FROM raw_appfolio_events ORDER BY pulled_at DESC LIMIT 10`
3. Check logs: `docker compose logs app`

### Notifications not sending
1. Verify `FEATURE_NOTIFICATIONS=true`
2. Check mail configuration in `.env`
3. Test with MailHog at http://localhost:8126 (development)

## License

Proprietary - All rights reserved.
