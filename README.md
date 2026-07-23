# DevXpert Lead Dashboard for Forminator & Contact Form 7

![Dashboard View](image-1.png)

**A lead-management add-on for the [Forminator](https://wordpress.org/plugins/forminator/) and [Contact Form 7](https://wordpress.org/plugins/contact-form-7/) WordPress plugins.**

Built by [Anup Kankale](https://anupkankale.com) · [DevXpert Labs](https://github.com/DevXpertLabs)

> **Status:** Live on the [WordPress.org plugin directory](https://wordpress.org/plugins/devxpert-lead-dashboard-for-forminator/).
> Requires WordPress 5.0+, PHP 7.4+, and Forminator **or** Contact Form 7 (either one is enough).

📖 **New here? Read the [User Guide](docs/user-guide.md)** — a plain-language walkthrough of installation and day-to-day lead management, written for non-developers.

---

## What Does This Plugin Do?

When someone fills out a form on your site, that submission becomes a **lead**. This plugin gives your team a proper dashboard inside WordPress to:

- See all leads in one place
- Mark leads as positive, negative, converted, and more
- Add feedback and notes on each lead
- Track how leads come in over time
- Export lead data to CSV
- Optionally require **email verification (OTP)** before a submission is accepted — to cut spam

Without it, form submissions just pile up in Forminator with no way to track what happened to them.

---

## Who Is It For?

| Role | What They Can Do |
|---|---|
| **Administrator** | Full access — dashboard, leads, settings, user management |
| **Sales Admin** | View all leads, update statuses, add feedback — nothing else |

Sales Admin users log in and land directly on the Lead Dashboard. They cannot access any other part of the WordPress admin.

---

## Key Features

### Dashboard Overview
Stats cards (Total / New / Positive / Negative / Conversion Rate), a "Leads Over Time" chart, a "Leads by Status" chart, and top forms by lead count.

![All Leads](image-2.png)

### All Leads
Paginated, filterable list (form, status, date range, assignee) with search. Click any lead to open its detail panel.

### Lead Detail Panel
All submitted fields, a status selector (New / Positive / Negative / Follow Up / Converted / Closed), rated feedback (positive / neutral / negative), full team feedback history, and an **activity log** of every change.

### CSV Export
Export any filtered view of leads, with all form fields as columns (spreadsheet-injection safe).

### New-Lead Automation
Optionally email your team when a new lead arrives, and/or auto-assign new leads to a default team member.

### Email OTP Spam Prevention
Optionally require visitors to verify their email via a one-time code before a submission becomes a lead. Codes are scoped per form + email, expire in 10 minutes, and are rate-limited. Uses `wp_mail()` with your own SMTP settings (pre-filled for Brevo, fully editable). The SMTP password is **encrypted at rest**.

### Sales Admin Role
Promote any WordPress user to a locked-down **Sales Admin** from the Settings page.

---

## Installation

1. Install and activate the free **Forminator** plugin.
2. Upload the `devxpert-lead-dashboard-for-forminator` folder to `/wp-content/plugins/` (or install the zip from **Plugins → Add New → Upload**).
3. Activate **DevXpert Lead Dashboard for Forminator**.
4. The database tables are created automatically; a **Lead Dashboard** menu appears in the sidebar.
5. Submit a test entry on any Forminator form — it shows up as a new lead.

---

## Requirements

- WordPress 5.0+
- PHP 7.4+
- [Forminator](https://wordpress.org/plugins/forminator/) (free)

---

## Project Structure

```
devxpert-lead-dashboard-for-forminator/
├── devxpert-lead-dashboard-for-forminator.php   # Main plugin file (singleton, hooks, AJAX)
├── uninstall.php                                # Drops tables + options on delete
├── readme.txt                                   # WordPress.org readme
├── includes/
│   ├── class-fld-roles.php          # sales_admin role + capability
│   ├── class-fld-database.php       # Custom table creation
│   ├── class-fld-leads.php          # Lead queries, stats, CSV export
│   ├── class-fld-feedback.php       # Feedback CRUD
│   ├── class-fld-otp.php            # Email OTP + SMTP + secret encryption
│   └── class-fld-notifications.php  # New-lead email + auto-assign
├── templates/                       # dashboard.php, leads.php, settings.php
├── assets/
│   ├── css/  (admin-styles.css, fld-otp.css)
│   └── js/   (admin-scripts.js, fld-settings.js, fld-otp.js, chart.min.js)
├── languages/                       # .pot translation template
└── tests/                           # PHPUnit smoke tests (dev only)
```

> All PHP globals are prefixed `dxleda_` / `DXLEDA_`; CSS classes/IDs use `fld-`. Data lives in three `{$wpdb->prefix}dxleda_*` tables; the raw submissions stay in Forminator's own tables.

---

## Development

No build step for the plugin — PHP is served directly; CSS/JS are plain enqueued files.

```bash
composer install                 # dev dependencies (PHPUnit + polyfills)
composer test                    # run the PHPUnit smoke tests (needs the WP test suite)
bash bin/install-wp-tests.sh wordpress_test <db_user> <db_pass> localhost latest
phpcs                            # WordPress-Extra + Docs + I18n + PHPCompatibility (phpcs.xml.dist)
```

Build a release zip (excludes dev files via `.distignore`):

```bash
wp dist-archive .
```

See **[HANDOVER.md](HANDOVER.md)** for full context, architecture notes, and the current TODO list.

---

## License

GPL-2.0-or-later — <https://www.gnu.org/licenses/gpl-2.0.html>
