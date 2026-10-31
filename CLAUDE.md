# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

HOAplugin Calendar (Free) — a WordPress plugin providing an HOA community events calendar (monthly grid + agenda views) with complex recurring-event support. PHP backend (namespace `HOAPLUGIN\Cal`, PSR-4 autoloaded from `includes/`), vanilla JS frontend, `rlanvin/php-rrule` for RFC 5545 recurrence.

This repo is one of three sibling repos that work together:
- `../hoaplugin-calendar-pro` — the commercial Pro plugin. Its `deploy.sh` rsyncs **this repo's files** into the Pro build and then overlays Pro files on top (same-path files in Pro win; `hoaplugin-calendar.php`, `readme.txt`, `README.md`, `deploy.sh` are excluded). So `includes/core-bootstrap.php`, `Repository.php`, `Compiler.php`, and the JS are shared code — changes here ship in Pro too. Free and Pro deactivate each other; `uninstall.php` refuses to drop data while Pro is active.
- `../hoaplugin-calendar-test` — test-harness plugin (Playwright + `TestRunner.php`). Testing was moved out of this repo.

## Commands

```bash
composer install && composer dump-autoload   # vendor/ is gitignored but required at runtime

./deploy.sh   # syncs version from plugin header -> readme.txt "Stable tag", rsyncs to
              # /var/www/html/wp-content/plugins/hoaplugin-calendar (sudo), builds
              # hoaplugin-calendar.zip and hoaplugin-calendar-wporg.zip.
              # If Version contains "RC" it stops there; otherwise it scp's the zip to the
              # production license server. Do not run a non-RC deploy without the user's go-ahead.
```

The version lives in the `* Version:` line of `hoaplugin-calendar.php`; `readme.txt` is updated from it by `deploy.sh`.

Tests (run from `../hoaplugin-calendar-test`, against the local WordPress testbed, so deploy/sync first):
```bash
./test.sh                                                       # full suite
npx playwright test --config=tests/playwright.config.js tests/calendar-logic.spec.js   # one spec
npx playwright test --config=tests/playwright.config.js tests/calendar-forms.spec.js -g "TEST 11B"  # one test
```
Test isolation: the `hoa_test_mode=1` cookie makes the test plugin hook `hoa_override_repo` / `hoa_override_compiler` / `hoa_calendar_json_path` to swap in a `{prefix}test_` table prefix and a separate JSON file. Always obtain Repository/Compiler via `hoa_get_repo()` / `hoa_get_compiler()` (never `new`) in request-handling code so this interception keeps working.

## Architecture

**Write path → bake → static JSON read path.** The frontend never queries the DB for display.
1. `assets/js/calendar-editor.js` posts to AJAX `hoa_save_calendar_event` with an `edit_mode` (`soft_save`, `instance_cancel`, `instance_move`, `master_cancel`, `series_end`, `series_resume`, `instance_restore`, `master_delete`, or default add/update).
2. `hoa_handle_save_event()` in `includes/core-bootstrap.php` does permission checks (admins = `manage_options`; delegates = emails listed in a category's `delegate_emails`, or `owner_email` on the event) and dispatches to `Repository` methods.
3. `Compiler::bake()` regenerates `wp-content/uploads/hoaplugin-calendar/calendar-events.json` (window = `hoa_cal_past_months`/`hoa_cal_future_months` options), and `hoa_cal_version` is bumped as a cache-buster.
4. `assets/js/calendar-view.js` fetches that JSON via AJAX `hoa_get_calendar_json` and renders month grid / agenda client-side. Config is passed in via `wp_localize_script` as `window.hoaplugin_data`.

**Data model** (`Repository::create_table()`): single `{prefix}hoaplugin_events` table with a parent/child structure, plus `hoaplugin_categories` and `hoaplugin_locations`. A master has `parent_id = NULL`. Children (all with `parent_id` = master id) are:
- **Pivot** — has an `rrule`; starts a new recurrence "era" from its `start_datetime` onward.
- **Hole** — `status = 'cancelled'`, no rrule; suppresses one instance.
- **Move** — active, no rrule; an out-of-sequence instance, always paired with a Hole at the natural time.

The master's `start_datetime` is the RRule anchor ("base date"), not necessarily the first instance. A pivot's date is the base date of the new sequence. Changing the rule ("DNA": start/end time or RRule) on the first instance of a master/pivot edits that record in place; on a later instance it creates a new pivot. Either way `delete_downstream()` removes all later exceptions. See the README's "Behaviors" and "Pivot Behavior" sections for the full edge-case spec (boomerang, triple-exception, shift-drag) — read them before changing `maybe_pivot_series` or `move_event_instance`.

**Compiler** (`compile_event_lineage`) walks RRule occurrences with a cursor while consuming the date-ordered exception queue for that master: pivot → swap rrule/anchor/duration; hole → skip; move → emit at the moved time. Instance metadata (title, category, etc.) always comes from the master row; children only carry scheduling.

**Free/Pro extension points** — Pro hooks into Free via filters/actions rather than editing core: `hoa_is_pro_active`, `hoa_pro_print_js_url`, `hoa_pro_print_data_url`, `hoa_background_url`, `hoa_enqueue_pro_scripts`, `hoaplugin_toolbar_export_area`, `hoa_calendar_extra_tabs` / `hoa_calendar_extra_tab_content` (admin settings tabs). Drag-and-drop saves are rejected unless `$GLOBALS['hoa_extended_buffer_enabled']` is set (Pro-only feature). Keep these hooks stable.

**Other pieces:** `admin/settings-page.php` is the `hoa-cal-settings` admin page (settings, Canva background zip upload, locations, categories with SVG icons, event audit log); saving settings triggers a re-bake. `hoa_export_event` serves `.ics`. `includes/Defaults.php` seeds sample data once on activation (`hoaplugin_defaults_installed`). Activation is multisite-aware (per-site tables).

## Conventions / constraints

- Shortcode is `[hoaplugin_calendar layout="hybrid|month|agenda"]`; `hoaplugin_monthly_calendar` and `hoaplugin_agenda_calendar` are legacy aliases that must keep working.
- PHP timezone is set from WordPress settings (`timezone_string` / `gmt_offset`) at the top of AJAX handlers and in `bake()`; datetimes in the DB are local wall-clock time.
- The `-wporg.zip` build is submitted to WordPress.org, so code must pass WP.org plugin review rules (sanitize input, escape output, nonce checks with `hoa_cal_nonce`, no remote calls/upsell abuse).
- `docs/USER_MANUAL.md` is the end-user manual; update it when user-visible behavior changes.
