# Changelog

All notable changes to **DevXpert Lead Dashboard for Forminator & Contact Form 7** are documented here.
The format is based on [Keep a Changelog](https://keepachangelog.com/); this project
follows the version in the plugin header / `readme.txt` `Stable tag`.

## [1.1.0] — Contact Form 7 support

### Added
- **Contact Form 7 as a lead source.** CF7 does not persist submissions — it mails
  them and discards them — so the plugin now captures them itself via
  `wpcf7_submit` into `dxleda_cf7_entries` / `dxleda_cf7_entry_meta`
  (`class-fld-cf7.php`). Only submissions received after upgrading are captured;
  there is no history to import.
- **`DXLEDA_Sources`** (`class-fld-sources.php`) — a source registry describing
  each supported form plugin's tables, availability, and form list. Nothing
  outside it names a vendor's tables or API.
- **Source filter and source badges** in the leads list and dashboard, shown only
  when more than one form plugin is active.
- `dxleda_lead_captured` action — the shared entry point both sources fire, so
  notifications and auto-assignment work identically for either.
- Tests covering the ID-collision cases (`tests/test-sources.php`).

### Changed
- **A lead is now identified by `(entry_id, source)`, not `entry_id` alone.**
  Entry IDs are only unique within one form plugin, so Forminator entry #5 and
  CF7 entry #5 would otherwise have shared status, feedback, and activity. The
  `source` column was added to `dxleda_lead_status`, `dxleda_feedback`, and
  `dxleda_activity_log`, and the unique key on `dxleda_lead_status` became
  `(entry_id, source)`.
- `dxleda_lead_status.source` (the lead's marketing origin) was renamed to
  `lead_source` to free the name for the form plugin identifier. Existing values
  are migrated.
- Schema upgrades now run on load via `DXLEDA_Database::maybe_upgrade()`, since an
  in-place plugin update never re-runs the activation hook.
- **Forminator is no longer required.** Either Forminator or Contact Form 7 is
  enough. The `Requires Plugins: forminator` header was removed because WordPress
  cannot express an either/or dependency and it would block CF7-only installs.
- Plugin renamed to *DevXpert Lead Dashboard for Forminator & Contact Form 7*.
  The directory slug is unchanged.
- CSV export gained Source and Form Name columns.

### Notes
- Email verification (OTP) remains Forminator-only; the Settings form list is
  filtered accordingly rather than listing CF7 forms that would be ignored.

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
