=== Opening Hours & Holidays ===
Contributors: simon61
Tags: opening hours, business hours, holidays, local seo, shortcode
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage regular opening hours and holiday exceptions, display them via shortcodes and add schema.org structured data for local SEO.

== Description ==

Opening Hours & Holidays lets you manage your business hours in one place and display them anywhere on your site.

**Features**

* Regular opening hours per weekday, with up to two time windows per day (e.g. morning and afternoon)
* Holiday exceptions: closed days or modified hours for specific dates, with an optional note
* Four shortcodes for different layouts: full weekly table, today only, upcoming holidays, and a compact list for footers or sidebars
* "Open now / Currently closed" badge
* Compatible with full-page caching (LiteSpeed Cache, WP Rocket and others) — the current day, badge and holiday filtering are applied in the browser
* Correct time zone handling: the status always reflects your business's local time, even for international visitors
* Optional schema.org LocalBusiness structured data (JSON-LD) with your opening hours for local SEO
* Customizable colors (badges, zebra stripes, text) — font and primary colors are inherited from your theme

**Shortcodes**

* `[opening_hours]` — weekly table, today highlighted. Attributes: `title`, `title_tag`
* `[opening_hours_today]` — today's hours with optional status badge
* `[opening_hours_holidays]` — upcoming holidays/exceptions. Attributes: `days` (default 30), `limit`, `title`, `title_tag`
* `[opening_hours_compact]` — compact list, consecutive days with identical hours are grouped. Attributes: `status`, `title`, `title_tag`

**Developers**

The schema.org output can be extended via the `ohhol_schema_data` filter, e.g. to add your address, telephone number or a more specific business type.

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

== Screenshots ==

1. Settings page with weekly hours and holiday exceptions
2. Weekly overview shortcode with highlighted current day
3. Compact list with status badge

== Changelog ==

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

= 2.0.0 =
Major update: plugin renamed, English interface with bundled German translation, schema.org support. Your existing settings and shortcodes keep working.
