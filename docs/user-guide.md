# DevXpert Lead Dashboard — User Guide

*A plain-language guide for site owners and sales teams. No coding knowledge needed.*

---

## 1. What this plugin does

Every time someone fills in a form on your website — a contact form, a quote request, an enquiry — that person is a **potential customer** (a "lead"). Normally those submissions just arrive as emails and pile up in an inbox where they are easy to lose.

This plugin collects every form submission in **one dashboard inside WordPress**, and lets you:

- See **every lead in one place**, from both Forminator and Contact Form 7 forms
- Mark each lead with a **status** (New, Follow Up, Converted, and more) so you always know where things stand
- **Assign leads** to a team member so nothing falls through the cracks
- Add **notes and ratings** to each lead after you talk to them
- **Export everything to a spreadsheet** (CSV) with one click
- Optionally **block spam** by making visitors confirm their email address before the form is accepted

Think of it as a simple sales pipeline that lives right inside your WordPress admin.

---

## 2. Before you start

You need:

| Requirement | Details |
|---|---|
| A WordPress website | With administrator access (you can log in to `/wp-admin`) |
| A form plugin | **Forminator** *or* **Contact Form 7** — either one is enough; both work together too |
| PHP 7.4 or newer | Almost all modern hosting has this; your host can confirm |

> **Don't have a form plugin yet?** In your WordPress admin go to **Plugins → Add New Plugin**, search for "Forminator" or "Contact Form 7", then click **Install Now** and **Activate**. Create at least one form and place it on a page.

---

## 3. Installation

### Method A — from the WordPress dashboard (recommended)

1. Log in to your WordPress admin (`yoursite.com/wp-admin`).
2. In the left menu go to **Plugins → Add New Plugin**.
3. In the search box (top right), type **"DevXpert Lead Dashboard"**.
4. Find **DevXpert Lead Dashboard for Forminator & Contact Form 7** and click **Install Now**.
5. When the button changes to **Activate**, click it.

That's it. A new **Lead Dashboard** item appears in your left admin menu.

### Method B — uploading the ZIP file

Use this if you downloaded the plugin as a `.zip` file (for example from [wordpress.org](https://wordpress.org/plugins/devxpert-lead-dashboard-for-forminator/)).

1. In your WordPress admin go to **Plugins → Add New Plugin**.
2. Click the **Upload Plugin** button at the top of the page.
3. Click **Choose File**, select the plugin `.zip` file, and click **Install Now**.
4. Click **Activate Plugin**.

### After activating

- If Forminator was already collecting submissions, **your existing entries appear straight away** — no import needed.
- Contact Form 7 normally doesn't store submissions at all, so for CF7 forms the dashboard shows **new submissions from this point on**. Older CF7 submissions only exist in your email inbox and can't be recovered.
- If neither form plugin is active you'll see a notice asking you to activate one — the dashboard stays hidden until you do.

---

## 4. Finding your way around

After activation, look at the left admin menu for **Lead Dashboard**. It has three pages:

| Page | What it's for |
|---|---|
| **Dashboard** | The big picture — totals, charts, and recent activity |
| **All Leads** | The full list of leads; this is where you'll spend most of your time |
| **Settings** | Spam protection (email verification) and maintenance tools — administrators only |

---

## 5. The Dashboard page

The Dashboard gives you an at-a-glance overview:

- **Stat cards** — how many leads you have in total and per status
- **Charts** — how leads are trending, so you can see busy and quiet periods
- If you use more than one form plugin, leads are labelled with a **source badge** (Forminator or Contact Form 7) so you can tell where each one came from

Use this page for a morning check-in: *How many new leads came in? How many are waiting for a follow-up?*

---

## 6. Managing your leads (the All Leads page)

This page lists every lead, newest first. Each row shows the person's details, which form they used, when they submitted, and their current status.

### Finding a specific lead

At the top of the list you can narrow things down:

- **Source** — show only Forminator or only Contact Form 7 leads (appears when both are active)
- **Form** — show leads from one particular form
- **Status** — e.g. show only "Follow Up" leads
- **Date from / to** — leads submitted in a date range
- **Search box** — type a name, email, or anything else the person entered

### Statuses — the heart of lead management

Every lead has one status. Change it as your conversation with the person progresses:

| Status | When to use it |
|---|---|
| **New** | Just arrived — nobody has looked at it yet |
| **Positive** | You've made contact and it looks promising |
| **Negative** | Not a good fit, or not interested |
| **Follow Up** | Waiting on something — call them back later |
| **Converted** | 🎉 They became a customer |
| **Closed** | Finished, no further action needed |

Keeping statuses up to date is the single most useful habit — it means anyone on the team can open the dashboard and instantly see what needs attention.

### Assigning leads to people

Each lead can be **assigned to a team member**, so it's always clear who owns the conversation. You can also set a **priority** to mark which leads deserve attention first.

### Notes and ratings (feedback)

Open a lead to see its full details. After you've spoken with the person, leave **feedback**: a rating — **Positive**, **Neutral**, or **Negative** — plus a written note ("Called Tuesday, asked for a quote, send proposal by Friday"). Notes from the whole team stay attached to the lead, so the history is never lost.

### Activity history

Every lead keeps a **timeline** of what happened: when it was assigned, when the status changed, and who did it. If you're ever unsure what's been done with a lead, the timeline tells you.

### Bulk actions

Tick several leads and use the **bulk action** menu to update them in one go — handy for clearing out old leads or marking a batch as closed.

### A simple daily routine

1. Open **Lead Dashboard → All Leads** and filter by status **New**.
2. Read each new lead, then assign it to the right person.
3. Contact the lead; afterwards set the status (**Positive**, **Negative**, or **Follow Up**) and leave a note.
4. Once a week, filter by **Follow Up** and work through the list.
5. When someone becomes a customer, set them to **Converted**.

---

## 7. Giving your sales team access

You probably don't want to give sales staff full administrator access to your website. You don't have to.

The plugin creates a WordPress role called **Sales Admin**:

- Sales Admins can see and manage the **Lead Dashboard** — and *nothing else* in your WordPress admin (no plugins, no settings, no pages)
- After logging in they land straight on the Lead Dashboard
- The **Settings** page stays visible to administrators only

To add a team member:

1. Go to **Users → Add New User**.
2. Fill in their details, and under **Role** choose **Sales Admin**.
3. Click **Add New User**. They can now log in and manage leads safely.

---

## 8. Exporting leads to a spreadsheet

Need your leads in Excel, Google Sheets, or another CRM?

1. Go to **Lead Dashboard → All Leads**.
2. (Optional) Apply filters first — the export respects them, so you can export e.g. only "Converted" leads from one form.
3. Click **Export CSV**.

The file downloads to your computer and includes each lead's details plus the form name, source, and status. Open it with any spreadsheet program.

---

## 9. Optional: block spam with email verification (OTP)

If a form gets a lot of fake or spam submissions, you can require visitors to **confirm their email address** before their submission is accepted. The visitor receives a one-time code (OTP) by email and must enter it — bots can't, so spam stops.

This is optional and off by default. To set it up you need free SMTP credentials from [Brevo](https://www.brevo.com) (or any SMTP provider):

1. Go to **Lead Dashboard → Settings** (administrators only).
2. Fill in the SMTP section: host, port, username, and password from your provider (the password is stored encrypted). For Brevo, the host `smtp-relay.brevo.com` and port `587` are pre-filled.
3. Set the **sender name and email** — what visitors will see in the verification email.
4. Tick the **forms** that should require verification. Forms you don't tick are unaffected.
5. Save.

From then on, ticked forms ask the visitor for their email code before the submission becomes a lead.

> **Tip:** Only enable OTP on forms with a spam problem. Each extra step costs you a few genuine submissions too.

---

## 10. Frequently asked questions

**Do I need both Forminator and Contact Form 7?**
No — either one is enough. If you run both, leads from both appear together, each labelled with its source.

**Will my old submissions show up?**
Forminator: yes, all stored entries appear immediately. Contact Form 7: no — CF7 never stored them, so only submissions made after installing this plugin are captured.

**Can two people work on leads at the same time?**
Yes. Statuses, notes, and assignments are shared — everyone sees the same up-to-date list.

**What happens if I deactivate the plugin?**
Nothing is lost. Your leads, statuses, and notes are kept in the database and reappear when you reactivate.

**What happens if I *delete* the plugin?**
Deleting (uninstalling from the Plugins page) removes the plugin's data — statuses, notes, activity history, and captured CF7 entries — permanently. Export a CSV first if you want a backup. Your Forminator entries are Forminator's own data and are not touched.

**The Lead Dashboard menu disappeared — why?**
The dashboard needs Forminator or Contact Form 7 to be active. If both are deactivated, the menu hides until one of them is active again.

**Is my SMTP password safe?**
Yes — it's stored encrypted, not as plain text.

---

## 11. Getting help

- Plugin page & support forum: [wordpress.org/plugins/devxpert-lead-dashboard-for-forminator](https://wordpress.org/plugins/devxpert-lead-dashboard-for-forminator/)
- Bug reports and suggestions: [GitHub issues](https://github.com/DevXpertLabs/Forminator-Lead-Dashboard-Add-on/issues)
