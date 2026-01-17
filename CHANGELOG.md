# Changelog

All notable changes to PMPulse will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

<!--
Add your changes here using these categories:

### Added
New features

### Changed
Changes in existing functionality

### Fixed
Bug fixes

### Removed
Removed features

Run `/changelog` in Claude Code to auto-generate entries from your branch changes.
-->

---

## [1.0.6] - 2026-01-17

### Changed
- Improved code architecture for better performance and maintainability

<!--
Add your changes here using these categories:

### Added
New features

### Changed
Changes in existing functionality

### Fixed
Bug fixes

### Removed
Removed features

Run `/changelog` in Claude Code to auto-generate entries from your branch changes.

Note: If changes are purely internal (refactoring, code cleanup, test improvements),
write a simple user-friendly summary like "Improved application stability" or
"Performance and reliability improvements" rather than leaving the section empty.
-->

---

## [1.0.5] - 2026-01-17

### Changed
- Improved property detail page loading performance

---

## [1.0.4] - 2026-01-16

### Fixed
- Bugs with deployment and testing

---

## [1.0.3] - 2026-01-16

### Added
- "What's New" page displaying release history with formatted dates and version badges

### Fixed
- Null pointer error when viewing work orders without completion dates

---

## [1.0.2] - 2026-01-16

### Added
- Configurable similarity threshold and result limit controls in vendor deduplication
- Background job processing for vendor duplicate analysis with progress indicator

### Changed
- Replaced browser alerts with toast notifications for better user feedback in vendor deduplication
- Improved accessibility for sortable table headers with screen reader announcements
- Improved accessibility for deduplication modal with keyboard navigation and focus trapping
- Optimized vendor compliance page to load categories directly from database
- Optimized vendor list page queries (45+ queries reduced to 2)
- Optimized vendor analytics queries for trends and period comparisons

---

## [1.0.1] - 2026-01-16

### Changed
- Improved test coverage with 200+ new tests across controllers, services, and models
- Test infrastructure now uses PostgreSQL for consistency with production environment

### Fixed
- Password change now properly invalidates other sessions before updating credentials
- Property adjustment end date validation now prevents setting end date before start date
- Vendor ranking queries now work correctly with PostgreSQL database
- Vendor list page now properly loads canonical vendor data when showing all vendors

---

## [1.0.0] - 2026-01-16

### Added
- Performance benchmark command for monitoring utility dashboard response times

### Changed
- Significantly improved utility dashboard performance (page loads 99% faster)

---

## [0.7.0] - 2026-01-14

### Added
- Bill details sync from AppFolio for improved expense tracking with unique transaction identifiers
- Admin Sync utilities tab for centralized sync management and history
- Custom date range options for syncs (6 months, 1 year, 2 years, all time, or custom dates)
- Ability to reset and rebuild utility expenses from bill details
- Enhanced sync history with expandable rows showing per-resource metrics and error details
- Vendor detail page with comprehensive metrics, insurance status, and contact information
- Vendor spend analysis charts with line/bar toggle and CSV export functionality
- Vendor spend breakdown by property with interactive pie chart visualization
- Vendor work order history with filtering by status and property, sortable columns, and pagination
- Days to complete column in work order history with color-coded indicators
- Vendor comparison view for side-by-side analysis of vendors within a trade
- Trade selector for filtering vendor comparisons
- Visual highlighting of best/worst vendor metrics in comparison view
- Trade averages summary in vendor comparison
- Vendor directory sync from AppFolio with contact info, trades, and insurance expiration tracking
- Work orders now linked to vendors with cost tracking (amount, vendor bill, estimate)
- Vendor deduplication support allowing multiple AppFolio vendor records to be linked as the same entity
- Canonical vendor grouping for accurate spend reporting across duplicate vendor records
- Vendor performance analytics with work order counts, total spend, and average cost metrics
- Period-over-period vendor comparisons (30-day, 90-day, 12-month, year-to-date)
- Vendor performance trend tracking with direction indicators
- Trade-based vendor analysis with grouping, averages, and within-trade ranking
- Vendor response time metrics including completion time breakdowns by priority
- Portfolio-wide vendor benchmarks for performance comparison
- Vendor list page with search, filtering by trade/insurance status/active status, and sortable columns
- Vendor insurance compliance report with categorized views for expired, expiring soon, and compliant vendors
- Workers comp tracking section in compliance report for focused insurance monitoring
- Do Not Use vendor list in compliance report highlighting vendors flagged as unusable
- Vendor deduplication management page for identifying and linking duplicate vendor records
- Potential duplicate finder using fuzzy matching on vendor names, phones, and emails
- Expandable duplicate groups on vendor list showing all linked vendor records
- Canonical vendor indicators and grouping filter on vendor list page
- Quick link/unlink actions for managing vendor duplicate relationships

### Changed
- Replaced expense register sync with bill details endpoint for better data integrity
- Consolidated admin panel: Integrations tab now includes AppFolio, Google Maps, and Google SSO with visual icons
- Moved sync history and schedule configuration to dedicated Sync tab
- Removed separate Authentication tab (now part of Integrations)

---

## [0.6.0] - 2026-01-06

### Added
- Utility expense tracking with GL account to utility type mappings
- Admin interface for configuring utility account mappings (water, electric, gas, garbage, sewer)
- Configurable utility types allowing custom categories beyond the defaults
- Admin page for managing utility types (add, edit, delete, reset to defaults)
- Automatic categorization of synced expenses as utility costs based on configured GL accounts
- Expenses now linked to utility accounts via foreign key for better data integrity
- GL account number stored on expenses for audit trail
- Automatic reclassification of expenses when utility account type is changed
- Expense register sync from AppFolio with date range filtering
- Utility expenses table for normalized cost tracking per property
- Utility cost analytics with per-unit and per-square-foot metrics
- Period-over-period comparison for utility costs (month, quarter, year-to-date)
- Portfolio-wide utility cost averages with statistical analysis (mean, median, standard deviation)
- Anomaly detection to identify properties with unusual utility costs
- Utility dashboard with portfolio-wide cost overview and summary cards by utility type
- Property utility detail view with cost breakdowns, period comparisons, and expense history
- Heat map table showing property utility costs relative to portfolio average with CSV export
- Anomaly alerts highlighting properties with unusually high or low utility costs
- Interactive trend charts for utility cost history with toggleable utility types
- Utility-specific property exclusions allowing properties to be excluded from specific utility types only
- Excluded properties list in utility dashboard showing all exclusions with reasons

---

## [0.5.0] - 2026-01-05

### Added
- Data adjustments feature allowing manual overrides of property metrics
- Support for adjusting unit count, square footage, market rent, and rentable units
- Date-ranged and permanent adjustments with full audit trail
- Analytics now respect property adjustments when calculating KPIs
- Adjustments management UI on property detail page (admin only)
- Create, edit, end, and delete adjustments via modal forms
- Active/historical adjustment tabs with audit trail display
- Visual indicators for adjusted values with tooltips showing original vs adjusted
- Portfolio-wide adjustments summary report in admin panel
- Filter adjustments by status, field type, creator, and date range
- CSV export of adjustments data

---

## [0.4.2] - 2026-01-05

### Added
- Automated release notes generation from CHANGELOG.md during production promotion
- `/changelog` command in Claude Code to generate changelog entries from branch changes
- `/pr-review` command in Claude Code to address PR comments and create tech debt issues

<!-- The horizontal rule below is used by the release script to separate the [Unreleased] section. Do not remove it. -->
---

## [0.3.0] - 2026-01-02

### Added
- User administration with role-based access control
- Admin, Manager, and Viewer roles with distinct permissions
- Google SSO authentication support
- Consolidated admin panel with tab navigation

### Changed
- Migrated AppFolio connection settings to unified Settings model
- Enhanced sync failure alerting with configurable thresholds

### Fixed
- Various sync reliability improvements

## [0.2.0] - 2025-12-30

### Added
- Property management dashboard with KPIs
- AppFolio API integration for data sync
- Incremental and full sync modes
- Business hours sync scheduling
- Property and unit data views

## [0.1.6] - 2025-12-28

### Added
- Initial release
- Basic authentication system
- Database schema foundation
