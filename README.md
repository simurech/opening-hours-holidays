# Opening Hours & Holidays

WordPress plugin to manage regular opening hours and holiday exceptions, display them via shortcodes and add schema.org structured data for local SEO.

Formerly known as "Öffnungszeiten Plugin" — settings from version 1.x are migrated automatically and the old German shortcodes keep working as aliases.

## Features

- Regular opening hours per weekday (up to two time windows per day)
- Holiday exceptions: closed days or modified hours for specific dates
- Four shortcodes for different layouts
- "Open now / Currently closed" badge
- Compatible with full-page caching (LiteSpeed, WP Rocket etc.)
- Correct time zone handling — also for international visitors
- Optional schema.org LocalBusiness JSON-LD output
- Customizable design (colors, zebra stripes, badges)
- Fully translatable; German translation bundled

## Shortcodes

| Shortcode | Purpose | Attributes |
|-----------|---------|------------|
| `[opening_hours]` | Weekly table, today highlighted | `title`, `title_tag` |
| `[opening_hours_today]` | Today's hours with optional status badge | – |
| `[opening_hours_holidays]` | Upcoming holidays/exceptions | `days` (30), `limit`, `title`, `title_tag` |
| `[opening_hours_compact]` | Compact list for footer/sidebar, equal days grouped | `status`, `title`, `title_tag` |

Legacy aliases: `[oeffnungszeiten]`, `[oeffnungszeiten_heute]`, `[oeffnungszeiten_spezial]` (incl. `tage` attribute), `[oeffnungszeiten_kompakt]`.

## Developers

Extend the schema.org output via the `ohhol_schema_data` filter:

```php
add_filter('ohhol_schema_data', function ($schema) {
    $schema['@type']     = 'Restaurant';
    $schema['telephone'] = '+41 ...';
    return $schema;
});
```

## Requirements

- WordPress 5.8+
- PHP 7.4+

## License

GPLv2 or later. See `readme.txt` for the full plugin directory readme.
