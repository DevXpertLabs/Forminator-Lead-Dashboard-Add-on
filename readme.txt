=== Lead Dashboard for Forminator ===
Contributors: anupkankale
Tags: forminator, leads, crm, lead management, dashboard
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.0.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn your Forminator form submissions into trackable leads with a simple sales dashboard, statuses, feedback notes, and CSV export.

== Description ==

**Built to solve a real problem.** A travel agency was collecting leads through their Forminator forms every day — and losing track of them. Spreadsheets got messy fast, and a full CRM was too expensive for what they actually needed. So instead of another monthly subscription, this plugin was born: put every lead in one place, right inside WordPress.

Lead Dashboard for Forminator gives your team one place to manage every Forminator form submission as a lead — so nothing gets missed.

It adds a **Lead Dashboard** menu to your WordPress admin with charts, an all-leads list, and a detail view for each submission.

**What you can do:**

* See lead stats and charts at a glance.
* Browse, search, and filter all leads (by form, status, date, or assignee).
* Open any lead to view its form fields, change its status, and add feedback.
* Track six statuses: New, Positive, Negative, Follow Up, Converted, Closed.
* Leave rated feedback notes (positive / neutral / negative) on each lead.
* Export leads to CSV.
* Give team members a locked-down **Sales Admin** role that only sees the dashboard.
* Optionally require email verification (OTP) before a submission counts as a lead.

Requires the free [Forminator](https://wordpress.org/plugins/forminator/) plugin.

== Installation ==

1. Install and activate the free **Forminator** plugin.
2. Upload the `lead-dashboard-for-forminator` folder to `/wp-content/plugins/`, or install it from the Plugins screen.
3. Activate **Lead Dashboard for Forminator**.
4. Open the new **Lead Dashboard** menu in your admin sidebar.

Tables are created automatically on activation. Submit a test entry on any Forminator form and it will show up as a new lead.

== Frequently Asked Questions ==

= Does it work with the free Forminator? =

Yes. The free version of Forminator is all you need.

= Where are my leads stored? =

Status, feedback, and activity are kept in the plugin's own tables. The form submissions stay in Forminator's tables, so your data is never duplicated or moved.

= Do I lose data if I deactivate the plugin? =

No. Your data stays on deactivation. It is only removed if you delete the plugin from **Plugins → Delete**.

= Can I have more than one Sales Admin? =

Yes. Assign the Sales Admin role to any users from **Lead Dashboard → Settings**.

= How does email verification (OTP) work? =

When enabled for a form, visitors get a 6-digit code by email and must enter it before submitting. Codes expire in 10 minutes and are rate-limited. It uses `wp_mail()` with your own SMTP settings (pre-filled for Brevo, but you can use any provider).

== Screenshots ==

1. Dashboard overview with stats and charts.
2. All Leads page with filters and search.
3. Lead detail view with status and feedback.
4. Settings page.

== Third-Party Libraries ==

Bundles Chart.js v4.4.0 (MIT) for the dashboard charts, loaded locally with no external requests.
Source: https://github.com/chartjs/Chart.js/releases/tag/v4.4.0

== Changelog ==

= 1.0.1 =
* Added optional email verification (OTP) with configurable SMTP.
* Added a per-form OTP toggle in Settings.
* Bug fixes.

= 1.0.0 =
* Initial release: dashboard, all-leads list, lead detail, statuses, feedback, CSV export, and the Sales Admin role.

== Upgrade Notice ==

= 1.0.1 =
Adds optional email verification. Safe to upgrade — no database changes.
