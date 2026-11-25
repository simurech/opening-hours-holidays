=== Opening Hours & Holidays ===
Contributors: simon61
Tags: opening hours, business hours, holidays, local seo, block
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage regular opening hours and holiday exceptions, display them via shortcodes and add schema.org structured data for local SEO.

== Description ==

Copying opening hours into your theme's footer, then updating them by hand every holiday season, gets tedious fast. Opening Hours & Holidays lets you manage your business hours in one place and display them anywhere on your site — with correct time zone handling, cache compatibility, and optional schema.org structured data for local search visibility.

**Features**

* Regular opening hours per weekday, with up to two time windows per day (e.g. morning and afternoon) — also past midnight
* Holiday exceptions: closed days or modified hours for single dates or **date ranges** (e.g. company holidays), optionally **repeating every year**
* **CSV import & export** for holidays — import a list of public holidays from a spreadsheet in seconds (template included)
* **"Opening Hours" block** for the block editor with live preview, plus five shortcodes
* "Open now / Currently closed" badge with **"Opens soon / Closes soon"** hint, updated live without reloading
* **Conditional content**: show text or buttons only while you are open or closed
* Compatible with full-page caching (LiteSpeed Cache, WP Rocket and others) — the current day, badge and holiday filtering are applied in the browser
* Correct time zone handling: the status always reflects your business's local time, even for international visitors
* Times follow your site's time format (12/24 hours) and first day of the week
* Optional schema.org structured data (JSON-LD) for local SEO: business type, address, telephone, regular and holiday hours
* Customizable colors (badges, zebra stripes, text) — font and primary colors are inherited from your theme
* No external requests, no tracking, no upsell

**Shortcodes**

* `[opening_hours]` — weekly table, today highlighted. Attributes: `title`, `title_tag`
* `[opening_hours_today]` — today's hours with optional status badge
* `[opening_hours_holidays]` — upcoming holidays/exceptions. Attributes: `days` (default 30), `limit`, `title`, `title_tag`
* `[opening_hours_compact]` — compact list, consecutive days with identical hours are grouped. Attributes: `status`, `title`, `title_tag`
* `[opening_hours_if_open]…[/opening_hours_if_open]` and `[opening_hours_if_closed]…[/opening_hours_if_closed]` — content shown only while open or closed. Attribute: `inline`

All views are also available as the **Opening Hours** block.

**Developers**

* `ohhol_schema_data` — filter the schema.org array, e.g. to add geo coordinates or a logo
* `ohhol_output_schema` — return false to skip the JSON-LD block on specific pages
* `ohhol_schema_conflicts` — adjust the detection of other plugins that output LocalBusiness data
* `ohhol_time_format` — change the PHP date format used to display times

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it via the Plugins screen.
2. Activate the plugin.
3. Configure your hours under the **Opening Hours** menu item.
4. Add the shortcodes to your pages, posts or widgets.

== Frequently Asked Questions ==

= Does the plugin work with caching plugins? =

Yes. The parts that depend on the current date and time (today's row, the "Open now" badge, holiday filtering) are rendered in the visitor's browser using your site's time zone, so cached pages always show correct information.

= Which time zone is used? =

The time zone configured in Settings → General. Visitors from other time zones see the status of *your* business's local time.

= How do I mark a public holiday? =

Add an entry under "Holidays & exceptions" with the date, choose "Closed" or "Modified hours", and optionally add a note such as "Public holiday".

= Does the plugin add structured data? =

Optionally, yes. Enable "Schema.org structured data" in the settings and the plugin outputs a LocalBusiness JSON-LD block with your regular hours and upcoming exceptions.

= How do I import public holidays? =

Under "Opening Hours", download the CSV template, fill in column A (date, e.g. 25.12.2026 or 2026-12-25) and column B (title), then upload it. You'll see a preview before anything is saved. The export contains all fields (ranges, modified hours, "every year") and can be imported again.

= Can I show a "Call us now" button only during opening hours? =

Yes: `[opening_hours_if_open]Call us now: …[/opening_hours_if_open]`. The content is shown or hidden in the browser, so it also works with page caching.

= Another SEO plugin already outputs LocalBusiness data. What should I do? =

The settings page shows a hint when Yoast Local SEO or Rank Math Local SEO is active. Enable structured data in only one plugin to avoid duplicate data.

= Can I manage opening hours for more than one location? =

Not currently. The plugin manages a single set of opening hours and holidays per site.

= Is there a Pro version with more features? =

No, this plugin is free and self-contained. There are no paid upgrades or premium add-ons.

== Screenshots ==

1. Weekly table with today highlighted and an upcoming holiday shown inline
2. Compact list for footers and sidebars with the live "Open now" badge
3. Settings: regular hours with two time windows per day
4. Holidays & exceptions with date ranges, yearly entries and CSV import
5. The "Opening Hours" block with live preview in the block editor

== Changelog ==

= 2.1.0 =
* New: "Opening Hours" block for the block editor (all four views, live preview)
* New: holidays and exceptions as date ranges (e.g. company holidays) and as yearly recurring entries
* New: CSV import with preview (template: date + title) and CSV export of all entries
* New: conditional content with `[opening_hours_if_open]` and `[opening_hours_if_closed]` (works with page caching)
* New: "Opens soon / Closes soon" hint in the status badge (threshold configurable)
* New: business type, address and telephone for the schema.org data, output on all pages or the front page only, hint when Yoast Local SEO or Rank Math Local SEO is active
* The `status` attribute of `[opening_hours_compact]` and `[opening_hours_today]` now overrides the global "Open now" option in both directions

= 2.0.5 =
* Minimum PHP version lowered to 7.4 again (the plugin does not need newer PHP features); minimum WordPress version is now 6.6
* Fix: a paragraph directly before a shortcode was hidden by the stylesheet
* New: opening hours past midnight (e.g. 22:00 – 02:00) are supported, incl. the "Open now" status
* New: times follow the site's time format (12/24 hours) and the weekly table starts on the configured first day of the week
* The "Open now / Currently closed" badge now updates every minute without reloading the page
* Incomplete time windows are detected, removed on save and reported; the settings page now shows the "Settings saved" notice
* Times of closed days are kept when a day is switched off
* Stylesheet is loaded in the page head when a shortcode is used in the content; script is loaded deferred
* Hardened JSON output, typed settings registration, multisite-aware uninstall

= 2.0.4 =
* Minimum PHP version raised to 8.3 (older PHP versions no longer receive security fixes)

= 2.0.3 =
* Confirmed compatibility with WordPress 7.1

= 2.0.2 =
* Hardened JSON-LD output escaping to prevent script-tag breakout via the business name

= 2.0.1 =
* Release pipeline now builds and attaches a ready-to-submit plugin ZIP to each GitHub release
* Added bundled LICENSE file (GPL-2.0-or-later)
* Defensive fallback for design colors in case a stored value is invalid
* Added index.php stub files to plugin subdirectories

= 2.0.0 =
* Plugin renamed to "Opening Hours & Holidays", ready for the WordPress.org plugin directory
* Full internationalization; German translation bundled
* New shortcode names (`opening_hours`, `opening_hours_today`, `opening_hours_holidays`, `opening_hours_compact`) — the previous German shortcodes keep working as aliases
* New: optional schema.org LocalBusiness structured data (JSON-LD)
* Holiday overlays in the weekly table are now applied client-side (correct with full-page caching)
* Settings from version 1.x are migrated automatically
* Stricter validation of dates, duplicate holiday entries are merged, entries more than 90 days in the past are cleaned up on save
* Accessibility: table captions, row headers, status badge announced to screen readers
* Uninstalling the plugin now removes all its options

= 1.1.2 =
* Fix: noscript fallback for the today shortcode

= 1.1.1 =
* Fix: no stale content with full-page caching enabled

= 1.1.0 =
* Today marker and status badge rendered via JavaScript (caching-compatible), business time zone used instead of the visitor's local time

== Upgrade Notice ==

= 2.1.0 =
New block, holiday date ranges and yearly entries, CSV import/export, conditional content and richer schema.org data. Existing settings and shortcodes keep working.

= 2.0.5 =
Runs on PHP 7.4+ again and requires WordPress 6.6+. Supports hours past midnight, the site's 12/24-hour time format and fixes a hidden paragraph before shortcodes.

= 2.0.4 =
Requires PHP 8.3 or higher. Sites on older PHP versions keep the current version until PHP is updated.

= 2.0.0 =
Major update: plugin renamed, English interface with bundled German translation, schema.org support. Your existing settings and shortcodes keep working.
