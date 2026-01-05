# Changelog

All notable changes to PMPulse will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Data adjustments feature allowing manual overrides of property metrics
- Support for adjusting unit count, square footage, market rent, and rentable units
- Date-ranged and permanent adjustments with full audit trail
- Analytics now respect property adjustments when calculating KPIs
- Adjustments management UI on property detail page (admin only)
- Create, edit, end, and delete adjustments via modal forms
- Active/historical adjustment tabs with audit trail display

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
