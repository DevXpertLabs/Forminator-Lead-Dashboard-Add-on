# Developer Guide — DevXpert Lead Dashboard

*Technical documentation for contributors and developers. For the user-facing guide, see the [README](../README.md).*

Built by [Anup Kankale](https://anupkankale.com) · [DevXpert Labs](https://github.com/DevXpertLabs)

> **Status:** Live on the [WordPress.org plugin directory](https://wordpress.org/plugins/devxpert-lead-dashboard-for-forminator/).
> Requires WordPress 5.0+, PHP 7.4+, and Forminator **or** Contact Form 7 (either one is enough).

---

## Architecture Overview

When someone fills out a Forminator or Contact Form 7 form, that submission becomes a **lead**. The plugin layers its own status/feedback/activity data on top of the submissions:

- **Forminator** persists its own entries; the plugin reads them in place from `{$wpdb->prefix}frmt_form_entry*`.
- **Contact Form 7** does not persist submissions, so the plugin captures them itself via the `wpcf7_submit` hook into its own `dxleda_cf7_entries` / `dxleda_cf7_entry_meta` tables.
- **`DXLEDA_Sources`** is the source registry — it describes each supported form plugin's tables, availability, and form list. Nothing outside it names a vendor's tables or API, so adding a third form plugin means extending the registry.
- Leads are identified by **entry ID + source**, so entries from different form plugins can never collide.
- Both capture paths fire the shared **`dxleda_lead_captured`** action.

## Roles & Capabilities

| Role | What They Can Do |
|---|---|
| **Administrator** | Full access — dashboard, leads, settings, user management |
| **Sales Admin** (`sales_admin`) | View all leads, update statuses, add feedback — nothing else |

Sales Admin users log in and land directly on the Lead Dashboard; the rest of the admin menu and toolbar is hidden from them. Promote any WordPress user from the Settings page.

## Key Features

### Dashboard Overview
Stats cards (Total / New / Positive / Negative / Conversion Rate), a "Leads Over Time" chart, a "Leads by Status" chart, and top forms by lead count. Source badges appear when more than one form plugin is active.

### All Leads
Paginated, filterable list (source, form, status, date range) with search. Click any lead to open its detail panel. Deep-linkable: `?page=dxleda-leads&entry=<id>&source=<slug>` opens that lead's panel on load, which is how the Telegram alert links back.

> `DXLEDA_Leads::get_leads()` also supports an `assigned_to` filter. It is reachable through the REST API but has no admin UI control yet.

### Lead Detail Panel
All submitted fields, a status selector (New / Positive / Negative / Follow Up / Converted / Closed), rated feedback (positive / neutral / negative), full team feedback history, and an **activity log** of every change (ordered newest first, id-tiebroken).

### CSV Export
Export any filtered view of leads, with all form fields as columns plus source and form name (spreadsheet-injection safe).

### New-Lead Automation
Optionally email your team when a new lead arrives, and/or auto-assign new leads to a default team member.

### Email OTP Spam Prevention
Optionally require visitors to verify their email via a one-time code before a submission becomes a lead. Codes are scoped per form + email, expire in 10 minutes, and are rate-limited. Verification tokens are bound to the form they were issued for. Uses `wp_mail()` with your own SMTP settings (pre-filled for Brevo, fully editable). The SMTP password is **encrypted at rest**.

### Telegram Alerts
Optional one-way push of each new lead to a Telegram chat (`DXLEDA_Telegram`). Hangs off the shared `dxleda_lead_captured` action, so both sources are covered without per-source code.

- **Delivered out-of-band.** `queue_alert()` runs the cheap checks and schedules a `dxleda_send_telegram_alert` single event; `send_alert()` performs the request. A form submission never blocks on Telegram.
- **HTML parse mode, not Markdown.** MarkdownV2 requires escaping ~18 characters and legacy Markdown breaks on a stray `_`/`*` in a submitted value; HTML needs only `&`, `<`, `>`.
- **Budgeted assembly.** Fields are added while a character budget lasts (4096 Telegram limit, 300 per field) rather than truncating the finished string, so a tag is never severed and the footer link always survives.
- **Credentials.** The bot token is encrypted at rest via `DXLEDA_OTP::encrypt_secret()` and constrained to Telegram's issued charset before entering the request path. The chat ID is stored as a string — group IDs are negative and channels may be `@name`.
- **Per-form opt-in** uses a `source|id` composite, since a form ID is only unique within its source. An empty list means all forms.
- **Failures** are recorded in `dxleda_activity_log` as `telegram_failed` with Telegram's own `description`, and surface in the lead's Activity panel. Note Telegram reports application errors with HTTP 200 and `ok:false`, so status code alone is not a success signal.

### REST API
Read-only routes under `dxleda/v1` (`DXLEDA_REST`), reusing the existing query layer:

| Route | Backed by |
|---|---|
| `GET /leads` | `DXLEDA_Leads::get_leads()` (all filters, `X-WP-Total` / `X-WP-TotalPages` headers) |
| `GET /leads/(?P<source>[a-z0-9_-]+)/(?P<id>\d+)` | `DXLEDA_Leads::get_lead()` |
| `GET /stats` | `DXLEDA_Leads::get_dashboard_stats()` |

All gated on `DXLEDA_Roles::CAP`; external clients use application passwords. There is no write surface.

> **Gotcha worth knowing:** every argument declares `validate_callback` explicitly. WordPress only wires up `rest_validate_request_arg` for args generated from a schema by `rest_get_endpoint_args_for_schema()` — on a hand-written `args` array, `enum`, `minimum` and `maximum` are silently ignored without it.

---

## Requirements

- WordPress 5.0+
- PHP 7.4+
- [Forminator](https://wordpress.org/plugins/forminator/) or [Contact Form 7](https://wordpress.org/plugins/contact-form-7/) (both free; either is enough, both together work too)

---

## Project Structure

```
devxpert-lead-dashboard-for-forminator/
├── devxpert-lead-dashboard-for-forminator.php   # Main plugin file (singleton, hooks, AJAX)
├── uninstall.php                                # Drops tables + options on delete
├── readme.txt                                   # WordPress.org readme
├── includes/
│   ├── class-dxleda-roles.php          # sales_admin role + capability
│   ├── class-dxleda-sources.php        # Source registry (Forminator / CF7 tables, availability, forms)
│   ├── class-dxleda-database.php       # Custom table creation
│   ├── class-dxleda-leads.php          # Lead queries, stats, CSV export
│   ├── class-dxleda-cf7.php            # CF7 submission capture (wpcf7_submit)
│   ├── class-dxleda-feedback.php       # Feedback CRUD
│   ├── class-dxleda-otp.php            # Email OTP + SMTP + secret encryption
│   ├── class-dxleda-notifications.php  # New-lead email + auto-assign
│   ├── class-dxleda-telegram.php       # New-lead Telegram alerts (cron-dispatched)
│   └── class-dxleda-rest.php           # Read-only dxleda/v1 REST routes
├── templates/                       # dashboard.php, leads.php, settings.php
├── assets/
│   ├── css/  (admin-styles.css, fld-otp.css)
│   └── js/   (admin-scripts.js, fld-settings.js, fld-otp.js, chart.min.js)
├── languages/                       # .pot translation template
├── docs/                            # This guide (dev only, not shipped)
└── tests/                           # PHPUnit smoke tests (dev only)
```

> All PHP globals are prefixed `dxleda_` / `DXLEDA_`; CSS classes/IDs use `fld-`. Data lives in five `{$wpdb->prefix}dxleda_*` tables (`lead_status`, `feedback`, `activity_log`, `cf7_entries`, `cf7_entry_meta`); raw Forminator submissions stay in Forminator's own tables.

---

## Development

No build step for the plugin — PHP is served directly; CSS/JS are plain enqueued files.

```bash
composer install                 # dev dependencies (PHPUnit + polyfills)
composer test                    # run the PHPUnit smoke tests (needs the WP test suite)
bash bin/install-wp-tests.sh wordpress_test <db_user> <db_pass> localhost latest
phpcs                            # WordPress-Extra + Docs + I18n + PHPCompatibility (phpcs.xml.dist)
```

CI (`.github/workflows/quality.yml`) runs four jobs on every PR: `php -l`, PHPCS, WordPress Plugin Check (against the release contents built via `.distignore`), and PHPUnit against the latest WP test suite.

Build a release zip (excludes dev files via `.distignore`):

```bash
wp dist-archive .
```

See **[HANDOVER.md](../HANDOVER.md)** for full context, architecture notes, and the current TODO list.

---

## License

GPL-2.0-or-later — <https://www.gnu.org/licenses/gpl-2.0.html>
