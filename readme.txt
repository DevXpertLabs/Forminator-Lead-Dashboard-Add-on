=== Lead Dashboard for Forminator ===
Contributors: anupkankale
Tags: forminator, leads, crm, dashboard, lead management
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.0.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A Lead Management Dashboard for Forminator. Track, categorise, and manage form submissions as leads with a dedicated sales-team interface.

== Description ==

**Lead Dashboard for Forminator** turns every Forminator form submission into a tracked lead. Instead of leads piling up with no follow-through, your sales team gets a purpose-built dashboard inside WordPress where they can act on every enquiry.

= Key Features =

* **Dashboard overview** — stats cards (Total, New, Positive, Converted leads), Leads Over Time chart, Status breakdown chart, and Top Forms table.
* **All Leads list** — paginated, filterable by form, status, date range, and assigned user. Full-text search across submission data.
* **Lead detail panel** — view all submitted form fields, change lead status, add rated feedback, read the full activity log.
* **Six lead statuses** — New, Positive, Negative, Follow Up, Converted, Closed.
* **Feedback & ratings** — sales team members can add notes with positive / neutral / negative ratings. Each feedback entry is logged.
* **CSV export** — export any filtered view of leads with all form fields as columns.
* **Sales Admin role** — a locked-down WordPress role that only sees the Lead Dashboard. Sales Admins cannot access any other admin page.
* **Email OTP spam prevention** — optionally require visitors to verify their email address (via a one-time code) before a submission is accepted as a lead. Sends codes through any SMTP provider (pre-configured for Brevo).
* **User management** — administrators can promote any WordPress user to Sales Admin directly from the Settings page.

= Requirements =

* WordPress 5.0 or higher
* PHP 7.4 or higher
* [Forminator](https://wordpress.org/plugins/forminator/) plugin (free version)

== Installation ==

1. Make sure the **Forminator** plugin is installed and activated.
2. Upload the `forminator-lead-dashboard` folder to `/wp-content/plugins/`.
3. Activate **Lead Dashboard for Forminator** from the WordPress Plugins screen.
4. The plugin creates its database tables automatically on activation.
5. A **Lead Dashboard** menu item will appear in the WordPress admin sidebar.

= First Steps =

1. Open any Forminator form and submit a test entry.
2. Go to **Lead Dashboard** — the test submission will appear as a new lead.
3. Click the lead to open the detail panel and explore the features.
4. Visit **Lead Dashboard → Settings** to configure notifications, OTP spam prevention, and assign Sales Admin users.

== Frequently Asked Questions ==

= Does this work with the free version of Forminator? =

Yes. The free version of Forminator from wordpress.org is all that is required.

= What happens to my leads if I deactivate the plugin? =

The plugin's own tables (`fld_lead_status`, `fld_feedback`, `fld_activity_log`) are preserved on deactivation so you can reactivate without losing data. To permanently delete all plugin data, use **Plugins → Delete** — this triggers the uninstall routine which drops the tables and removes all plugin options.

= Can I have multiple Sales Admin users? =

Yes. Go to **Lead Dashboard → Settings → Sales Admin Users** and assign the role to as many users as you need. Each user will be limited to the Lead Dashboard when they log in.

= How does Email OTP spam prevention work? =

When enabled for a form, a "Send Verification Code" widget is injected above the form's Submit button. The visitor must receive and enter a 6-digit code sent to their email before the form submission is accepted. The code is valid for 10 minutes and is rate-limited to 5 sends per IP per hour.

= Which SMTP provider does the OTP feature use? =

It uses WordPress's `wp_mail()` function with a configurable SMTP backend. The default configuration targets Brevo (smtp-relay.brevo.com, port 587, TLS), but you can change it to any SMTP provider via **Settings → Spam Prevention**.

= Is the lead data stored in WordPress's database? =

Lead *status*, *feedback*, and *activity* data are stored in three custom tables prefixed with `{prefix}fld_`. The raw form submissions remain in Forminator's own tables (`frmt_form_entry` / `frmt_form_entry_meta`).

== Screenshots ==

1. Lead Dashboard overview with stats and charts.
2. All Leads page with filters and search.
3. Lead detail modal showing form fields, status selector, and feedback panel.
4. Settings page — General, Notification, OTP Spam Prevention, and User Management sections.

== Third-Party Libraries ==

This plugin bundles **Chart.js** (v4.4.0) to render the dashboard charts locally, so no
external CDN is contacted. Chart.js is released under the MIT License.

* File: `assets/js/chart.min.js`
* Source & unminified code: https://github.com/chartjs/Chart.js/releases/tag/v4.4.0
* Project home: https://www.chartjs.org/

== Changelog ==

= 1.0.1 =
* Added Email OTP spam prevention feature with configurable SMTP backend.
* Added per-form OTP toggle in Settings.
* Fixed critical JS selector bug that prevented the OTP widget from appearing on Forminator forms.

= 1.0.0 =
* Initial release.
* Lead Dashboard with stats and charts.
* All Leads page with filtering, search, and pagination.
* Lead detail modal with status management, feedback, and activity log.
* CSV export.
* Sales Admin role with locked-down WP admin.
* User management from the Settings page.

== Upgrade Notice ==

= 1.0.1 =
Adds optional Email OTP spam prevention. No database changes. Safe to upgrade.
