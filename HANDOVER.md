# HANDOVER — DevXpert Lead Dashboard for Forminator

_Last updated: 2026-07-11_

Context doc for picking this project back up. Read this first before making changes.

---

## 1. What this is

A WordPress plugin — a **lead-management add-on for the free [Forminator](https://wordpress.org/plugins/forminator/) plugin**. Every Forminator form submission becomes a trackable "lead" with a dashboard, statuses, feedback notes, an activity log, CSV export, a locked-down Sales Admin role, and an optional email-OTP spam gate.

- **Display name:** DevXpert Lead Dashboard for Forminator
- **Slug / text domain:** `devxpert-lead-dashboard-for-forminator`
- **Main file:** `devxpert-lead-dashboard-for-forminator.php`
- **Main class:** `DevXpert_Lead_Dashboard` (singleton)
- **Version:** 1.0.1
- **Requires:** WordPress 5.0+, PHP 7.4+, Forminator (declared via `Requires Plugins: forminator`)
- **Install path:** `wp-content/plugins/devxpert-lead-dashboard-for-forminator/`

## 2. Current status (IMPORTANT)

✅ **Submitted to the WordPress.org plugin directory on 2026-07-11 and passed the automated scan. Now AWAITING MANUAL REVIEW.**

- Do **not** resubmit while it's in the queue. If something needs changing before approval, reply to the WordPress.org automated email (from `plugins@wordpress.org`).
- Review email goes to **anupkankaleak47@gmail.com**, subject "Review in Progress: DevXpert Lead Dashboard for Forminator".
- The assigned slug becomes **permanent on approval** — do not change it now.

## 3. Naming history (why things are named what they are)

The plugin was renamed **twice** for WordPress.org compliance — grep for the old names if you find stragglers:

1. Original: "Forminator Lead Dashboard by DevXpert", slug `forminator-lead-dashboard` → rejected pattern (starts with the "Forminator" trademark).
2. First rename: "Lead Dashboard for Forminator", slug `lead-dashboard-for-forminator` → still risky (generic leading term).
3. **Current:** "DevXpert Lead Dashboard for Forminator", slug/text-domain `devxpert-lead-dashboard-for-forminator`. Brand-led ("DevXpert") + correct "for Forminator" tail. Main class renamed `Forminator_Lead_Dashboard` → `DevXpert_Lead_Dashboard`.

**Prefix migration (2026-07-12):** all collision-prone globals were prefixed with `dxleda_`/`DXLEDA_` per WordPress.org review — classes (`DXLEDA_Roles`, `DXLEDA_Leads`, `DXLEDA_OTP`, …), constants (`DXLEDA_VERSION`, `DXLEDA_PLUGIN_DIR`, …), options/transients/AJAX actions/nonces, script & style handles (`dxleda-*`), the localized JS objects (`dxleda_ajax`, `dxleda_otp_config`, `dxleda_settings_l10n`), custom tables (`{$wpdb->prefix}dxleda_*`), the role (`dxleda_sales_admin`) and cap (`dxleda_manage_leads`), and admin menu/page slugs (`dxleda-dashboard`, `dxleda-leads`, `dxleda-settings`). **CSS classes/IDs and asset/class filenames are intentionally left as `fld-`/`class-fld-*`** — presentation only, no collision risk. The encrypted-secret value marker is still `fldenc:` (an internal value, not a global).

## 4. Architecture

Bootstrapped by the `DevXpert_Lead_Dashboard` singleton in the main file. Load order on `plugins_loaded`: (1) `FLD_Roles::setup()` at priority 5, (2) Forminator active-check + `includes()` + menus/assets/AJAX registration, (3) activation hook creates DB tables.

### PHP classes (`includes/`)
| Class | File | Responsibility |
|---|---|---|
| `DevXpert_Lead_Dashboard` | main file | Singleton; hooks, admin menus, all AJAX handlers |
| `FLD_Roles` | `class-fld-roles.php` | `sales_admin` role + `fld_manage_leads` cap; auth helpers |
| `FLD_Database` | `class-fld-database.php` | Table creation/schema/drop |
| `FLD_Leads` | `class-fld-leads.php` | Lead queries, stats, CSV export, activity log |
| `FLD_Feedback` | `class-fld-feedback.php` | Feedback CRUD |
| `FLD_OTP` | `class-fld-otp.php` | Email OTP + SMTP + secret encryption |
| `FLD_Notifications` | `class-fld-notifications.php` | New-lead email + auto-assign |

### Database (3 owned tables, all `{$wpdb->prefix}dxleda_`)
- `dxleda_lead_status` — one row per entry: `status`, `assigned_to`, `priority`, `source`.
- `dxleda_feedback` — many rows per entry: text, `rating` (positive/neutral/negative), `user_id`.
- `dxleda_activity_log` — append-only log of status/feedback/assignment actions.

Lead data itself lives in Forminator's `frmt_form_entry` / `frmt_form_entry_meta`. `FLD_Leads::get_leads()` LEFT JOINs these so entries with no status row still show as `new`.

### AJAX pattern
All via `admin-ajax.php`. Every handler: `check_ajax_referer('fld_nonce','nonce')` + `FLD_Roles::can_access()` (or `is_admin()` for admin-only actions). Nonce injected via `wp_localize_script` as `fld_ajax.nonce`. Valid statuses (PHP allowlist): `new, positive, negative, follow_up, converted, closed`.

### JS / assets
`assets/js/admin-scripts.js` — single jQuery IIFE, detects page by DOM (`.fld-dashboard` / `.fld-leads-page`). Chart.js bundled locally at `assets/js/chart.min.js` (v4.4.0, MIT — **no CDN**, WordPress.org forbids it). Rating icons are inline SVGs (`.fld-rating-ico--positive/neutral/negative`), not emoji.

## 5. What was done (feature/hardening history)

- **WordPress.org compliance:** escape all output, sanitize + `wp_unslash` all input, nonces on all AJAX, direct-access guards, removed dev cruft.
- **Tier 1 — finished half-built features:** new-lead email + auto-assign (`FLD_Notifications`), wired the Settings "Database Tools" buttons (clear log / reset statuses), populated the Activity Log panel (`fld_get_activity`).
- **Tier 2 — performance:** killed N+1 queries in `get_leads()` (batched `get_entry_meta_bulk()` + `get_feedback_counts()`); cached Forminator form names (`FLD_Leads::form_names()`).
- **Tier 3 — security:** SMTP password **encrypted at rest** (AES-256-CBC, key from `wp_salt('auth')`); CSV **formula-injection** escaping; status allowlist enforced in the data layer.
- **Tier 5 — dev process:** LICENSE (GPL-2.0), `.pot`, PHPCS ruleset, PHPUnit smoke tests, CI workflow, `.distignore`.
- **UI:** replaced 👍😐👎 emoji with premium line-style SVG rating icons.

## 6. Dev setup

No build step for the plugin itself — PHP is served directly; CSS/JS are plain enqueued files.

Dev tooling (all excluded from the release zip):
```bash
composer install                 # PHPUnit + polyfills
composer test                    # runs PHPUnit (needs WP test suite)
bash bin/install-wp-tests.sh wordpress_test <db_user> <db_pass> localhost latest
phpcs                            # uses phpcs.xml.dist (WordPress-Extra + Docs + I18n + PHPCompatibilityWP)
```
Tests live in `tests/` (`test-database.php`, `test-leads.php`, `test-feedback.php`, `test-otp.php`). Local full run needs `svn` + a DB the test-user can create; CI handles both.

## 7. Building the release zip

The zip must contain ONLY production files (dev files trigger Plugin Check `hidden_files` / `application_detected` errors). Exclusions are in `.distignore`.

- Preferred: `wp dist-archive .` (needs `wp package install wp-cli/dist-archive-command`).
- Fallback used previously (zip/unzip not installed on this box) — copy git-tracked files minus `.distignore` entries into a folder named exactly `devxpert-lead-dashboard-for-forminator/` and zip with Python `shutil.make_archive`.

**Ships (19 files):** main file, `includes/`, `templates/`, `assets/`, `languages/*.pot`, `readme.txt`, `LICENSE`, `uninstall.php`.
**Excluded:** `.git`, `.github`, `.claude`, `.gitignore`, `.distignore`, `phpcs.xml.dist`, `phpunit.xml.dist`, `composer.*`, `bin/`, `tests/`, `README.md`, `HANDOVER.md`, `image*.png`.

Last built clean zip: `/home/anup/devxpert-lead-dashboard-for-forminator.zip` (~129 KB).

## 8. Git

- Remote: `origin` → https://github.com/DevXpertLabs/Forminator-Lead-Dashboard-Add-on.git (branches `main`, `develop`, open PR #1).
- Working branch: **`wporg-compliance`** (10 commits ahead of `origin/main`), based on `origin/main`.
- Commit author: Anupkankale <anupkankaleak47@gmail.com>.

⚠️ **Two gotchas:**
1. **`.github/workflows/quality.yml` is UNTRACKED on purpose.** The PAT used for pushing lacks the `workflow` scope, and GitHub rejects any push that adds a workflow file. It was excised from history. To add CI: either add the `workflow` scope to the PAT and commit it, or paste the file via GitHub's web "Add file" editor. Until then, **do not `git add -A`** — it re-stages the workflow and the next push fails. Stage specific paths, or `git restore --staged .github` after `git add -A`.
2. **Pushing needs your GitHub credentials** (no `gh` CLI, no cached token in this environment). Run `git push -u origin wporg-compliance` yourself in a terminal; enter username + a `repo`-scoped PAT.

## 9. Open items / TODO

- [ ] **Live-test the new-lead email** — `FLD_Notifications` hooks `forminator_form_after_save_entry($form_id, $response)` and reads `$response['entry_id']`. This is the widely-used hook but was **never verified end-to-end** here (no live Forminator submission tested). It fails safe (no error) if the signature differs. **Verify on a real submission**; if it doesn't fire, adjust the hook.
- [ ] **Push `wporg-compliance`** to GitHub and open a PR into `develop`.
- [ ] **Add the CI workflow** (see gotcha #1 above).
- [ ] **After approval:** add `screenshot-1..4.png` (+ optional `banner-772x250.png`, `icon-256x256.png`) to the SVN `assets/` dir (NOT the plugin zip); copy files to SVN `trunk/`, tag `tags/1.0.1`.
- [ ] **Confirm `Tested up to`** in `readme.txt` matches the current WP version (set to `7.0` because Plugin Check reported that; verify).
- [ ] **SMTP password:** existing installs with a saved password should re-save it once to upgrade legacy plaintext to encrypted (old plaintext still works).
- [ ] Ensure **anupkankale.com** (Author URI) resolves — reviewers may click it.

## 10. Known-acceptable Plugin Check output

On the built zip: automated scan **passes**. Remaining are ~95 **warnings** only — all `WordPress.DB.DirectDatabaseQuery.*` / `InterpolatedNotPrepared` / `UnescapedDBParameter` on the custom + Forminator tables. These are expected false-positives (custom tables have no core API; all values are bound via `$wpdb->prepare()`, table names come only from `$wpdb->prefix`). Reviewers accept them. The Additional Information note submitted with the plugin explains this.

If you run Plugin Check on the **dev folder** (not the zip) you'll also see ~7 `hidden_files` / `application_detected` errors — those are the dev files and vanish in the built zip.
