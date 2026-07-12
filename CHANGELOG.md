# Changelog

All notable changes to **DevXpert Lead Dashboard for Forminator** are documented here.
The format is based on [Keep a Changelog](https://keepachangelog.com/); this project
follows the version in the plugin header / `readme.txt` `Stable tag`.

## [Unreleased] — WordPress.org submission prep

Prepared the plugin for the WordPress.org directory (submitted 2026-07-11, in review).

### Added
- **New-lead automation** — optional email notification and auto-assignment when a
  Forminator submission becomes a lead (`class-fld-notifications.php`).
- **Activity log panel** in the lead detail modal, backed by a `dxleda_get_activity`
  endpoint.
- **Database Tools** in Settings — Clear Activity Log and Reset All Statuses
  (admin-only, nonce-checked, with confirmations).
- Premium inline-SVG feedback rating icons (replacing emoji).
- Dev tooling: PHPUnit smoke tests, `phpcs.xml.dist`, `.pot` template, `.distignore`,
  GPL-2.0 `LICENSE`.

### Changed
- **Renamed** to *DevXpert Lead Dashboard for Forminator*
  (slug/text-domain `devxpert-lead-dashboard-for-forminator`) for directory
  naming/trademark compliance.
- **Prefixed all globals** with `dxleda_` / `DXLEDA_` — classes, constants, options,
  transients, AJAX actions, nonces, script/style handles, custom tables, role, and
  capability — to avoid collisions.
- Updated bundled **Chart.js 4.4.0 → 4.5.1**.
- Enqueued the Settings page script instead of inlining it.
- Performance: eliminated N+1 queries in the leads list (batched meta + feedback
  counts) and cached Forminator form names.

### Security
- Email **OTP scoped per form + email** — codes and verification tokens are bound to
  the specific form, so a code/token issued for one form cannot satisfy another.
- SMTP password **encrypted at rest** (AES-256-CBC, key derived from `wp_salt`).
- **CSV formula-injection** protection on export.
- Hardened throughout: all output escaped, all input sanitized + unslashed, nonces +
  capability checks on every AJAX action, status allowlist enforced in the data layer.

## [1.0.1]

- Added Email OTP spam prevention with a configurable SMTP backend.
- Added a per-form OTP toggle in Settings.
- Bug fixes.

## [1.0.0]

- Initial release: Lead Dashboard with stats and charts, All Leads list with
  filtering/search/pagination, lead detail modal (status, feedback, activity log),
  CSV export, the Sales Admin role, and user management from Settings.
