=== DevXpert Lead Dashboard for Forminator & Contact Form 7 ===
Contributors: anupkankale
Tags: forminator, contact form 7, leads, crm, lead management
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 1.2.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn your Forminator and Contact Form 7 submissions into trackable leads with a simple sales dashboard, statuses, feedback notes, and CSV export.

== Description ==

**Built to solve a real problem.** A travel agency was collecting leads through their Forminator forms every day — and losing track of them. Spreadsheets got messy fast, and a full CRM was too expensive for what they actually needed. So instead of another monthly subscription, this plugin was born: put every lead in one place, right inside WordPress.

DevXpert Lead Dashboard gives your team one place to manage every form submission as a lead — from **Forminator**, **Contact Form 7**, or both at once — so nothing gets missed.

It adds a **Lead Dashboard** menu to your WordPress admin with charts, an all-leads list, and a detail view for each submission.

**What you can do:**

* Get a **Telegram alert on your phone** the moment a lead arrives.
* See lead stats and charts at a glance.
* Browse, search, and filter all leads (by form, status, date, or assignee).
* Open any lead to view its form fields, change its status, and add feedback.
* Track six statuses: New, Positive, Negative, Follow Up, Converted, Closed.
* Leave rated feedback notes (positive / neutral / negative) on each lead.
* Export leads to CSV.
* Give team members a locked-down **Sales Admin** role that only sees the dashboard.
* Optionally require email verification (OTP) before a submission counts as a lead (Forminator forms only).
* Filter by source when you run both form plugins side by side.
* Read your leads from outside WordPress through a read-only REST API.

Requires either [Forminator](https://wordpress.org/plugins/forminator/) or [Contact Form 7](https://wordpress.org/plugins/contact-form-7/) — both are free, and either one on its own is enough.

== Installation ==

1. Install and activate **Forminator**, **Contact Form 7**, or both.
2. Upload the `devxpert-lead-dashboard-for-forminator` folder to `/wp-content/plugins/`, or install it from the Plugins screen.
3. Activate **DevXpert Lead Dashboard**.
4. Open the new **Lead Dashboard** menu in your admin sidebar.

Tables are created automatically on activation. Submit a test entry on any supported form and it will show up as a new lead.

== Frequently Asked Questions ==

= Does it work with the free Forminator? =

Yes. The free version of Forminator is all you need.

= Do I need both Forminator and Contact Form 7? =

No. Either one is enough. If you have both, leads from both appear together and you can filter by source.

= Will my old Contact Form 7 submissions appear? =

No. Contact Form 7 does not store submissions anywhere — it emails them and discards them. This plugin starts saving CF7 submissions from the moment it is activated, so there is no past history to import.

= Where are my leads stored? =

Status, feedback, and activity are kept in the plugin's own tables. Forminator submissions stay in Forminator's tables, so that data is never duplicated or moved. Contact Form 7 submissions are stored in the plugin's own tables, because CF7 does not keep them itself.

= Does email verification (OTP) work with Contact Form 7? =

Not yet. OTP is currently available for Forminator forms only.

= How do I set up Telegram alerts? =

Message @BotFather on Telegram and send `/newbot` to create a bot — it replies with a token. Then message @userinfobot to get your own chat ID, or add your new bot to a group and use that group's ID. Paste both into **Lead Dashboard → Settings → Telegram Alerts**, save, and press **Send test message** to confirm. Leave the form checkboxes empty to get alerts from every form.

= Can I reply to a lead from Telegram? =

Not currently. Alerts are one-way: the message shows you the submission and links straight to that lead in your dashboard, where you change status and add feedback. This keeps the plugin from having to expose a public callback endpoint on your site.

= My Telegram alerts are delayed or not arriving. What should I check? =

First press **Send test message** in Settings — it shows Telegram's own error text, such as "chat not found" or "Unauthorized", which usually identifies the problem immediately. If the test works but real leads are slow, alerts are sent in the background via WP-Cron, so a site with `DISABLE_WP_CRON` set and no system cron running will delay them. Failed deliveries are recorded in each lead's Activity Log.

= Is there an API? =

Yes, read-only. Authenticated requests to `/wp-json/dxleda/v1/leads`, `/wp-json/dxleda/v1/leads/{source}/{id}`, and `/wp-json/dxleda/v1/stats` return your lead data as JSON. Use a WordPress application password, and note that the account must have Lead Dashboard access. The API cannot change anything.

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

== External Services ==

**Telegram Bot API — optional, off by default.**

This plugin can send new-lead alerts to Telegram. It is disabled until you turn it on and supply your own Telegram bot token and chat ID; if you never enable it, the plugin makes no external requests at all.

When enabled, the plugin sends a request to the Telegram Bot API at `https://api.telegram.org` each time a lead is captured from a form you have opted in, and once more each time you press the "Send test message" button in Settings.

What is sent: the submitted form field values for that lead, the form name, the entry ID, and a link back to your own dashboard. The message goes only to the chat ID you configured. Nothing is sent to the plugin author or to any other third party, and no data is sent on any other event.

This service is provided by Telegram. By enabling it you agree to their terms:
Terms of Service: https://telegram.org/tos
Privacy Policy: https://telegram.org/privacy
Bot API Terms: https://telegram.org/tos/bot-developers

== Third-Party Libraries ==

Bundles Chart.js v4.5.1 (MIT) for the dashboard charts, loaded locally with no external requests.
Source: https://github.com/chartjs/Chart.js/releases/tag/v4.5.1

== Changelog ==

= 1.2.0 =
* Added optional Telegram alerts — get every new lead pushed to your phone the moment it arrives, with a link straight to that lead in the dashboard.
* Alerts can be limited to specific forms, and a "Send test message" button confirms your setup before you rely on it.
* Alerts are sent in the background so form submissions stay fast, and delivery failures are recorded in the lead's Activity Log.
* Added a read-only REST API (`/wp-json/dxleda/v1/`) for reading leads and stats from outside WordPress.
* Fixed: the Date From and Date To filters on the All Leads screen did nothing.

= 1.1.0 =
* Added Contact Form 7 support: CF7 submissions are now captured and managed as leads alongside Forminator.
* Added a Source filter and source badges so you can tell where each lead came from.
* CSV export now includes the source and form name.
* Leads are now identified by entry ID *and* source, so entries from different form plugins can no longer overwrite each other's status, feedback, or activity.
* Forminator is no longer a hard requirement — either supported form plugin will do.

= 1.0.1 =
* Added optional email verification (OTP) with configurable SMTP.
* Added a per-form OTP toggle in Settings.
* Bug fixes.

= 1.0.0 =
* Initial release: dashboard, all-leads list, lead detail, statuses, feedback, CSV export, and the Sales Admin role.

== Upgrade Notice ==

= 1.2.0 =
Adds optional Telegram alerts for new leads and a read-only REST API. Safe to upgrade — no database changes, and Telegram stays off until you enable it.

= 1.1.0 =
Adds Contact Form 7 support. This upgrade changes the database schema; existing leads are migrated automatically and keep their status, feedback, and history.

= 1.0.1 =
Adds optional email verification. Safe to upgrade — no database changes.
