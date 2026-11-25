# Opening Hours & Holidays

**Öffnungszeiten und Feiertags-Ausnahmen verwalten und per Shortcode anzeigen — inklusive schema.org-Daten fürs lokale SEO.**

[![Release](https://img.shields.io/github/v/release/simurech/opening-hours-holidays?label=stable)](https://github.com/simurech/opening-hours-holidays/releases)
[![WordPress](https://img.shields.io/badge/WordPress-6.6%2B-21759b.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-8892be.svg)](https://php.net/)
[![Lizenz](https://img.shields.io/badge/Lizenz-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

> Öffnungszeiten und Feiertage einmal im Backend pflegen, per Shortcode überall auf der Website anzeigen — mit korrekter Zeitzonen-Behandlung, Cache-Kompatibilität und optionalen schema.org-Strukturdaten für lokale Suchmaschinen.

**→ Plugin auf WordPress.org (in Vorbereitung)** — Einreichung ist vorbereitet, aber noch nicht freigegeben.

Ehemals bekannt als „Öffnungszeiten Plugin" — Einstellungen aus Version 1.x werden automatisch migriert, die alten deutschen Shortcodes funktionieren als Aliase weiter.

---

## Funktionen

- Reguläre Öffnungszeiten pro Wochentag (bis zu zwei Zeitfenster pro Tag, auch über Mitternacht)
- Zeitformat (12/24 h) und Wochenbeginn gemäss WordPress-Einstellungen
- Feiertags-Ausnahmen: geschlossene Tage oder angepasste Zeiten für bestimmte Daten
- Vier Shortcodes für unterschiedliche Layouts
- „Jetzt geöffnet / Aktuell geschlossen"-Badge, aktualisiert sich live
- Kompatibel mit Full-Page-Caching (LiteSpeed, WP Rocket etc.)
- Korrekte Zeitzonen-Behandlung — auch für internationale Besucher
- Optionale schema.org-LocalBusiness-Ausgabe (JSON-LD)
- Anpassbares Design (Farben, Zebra-Streifen, Badges)
- Vollständig übersetzbar; deutsche Übersetzung mitgeliefert

---

## Shortcodes

| Shortcode | Zweck | Attribute |
|-----------|-------|-----------|
| `[opening_hours]` | Wochentabelle, heutiger Tag hervorgehoben | `title`, `title_tag` |
| `[opening_hours_today]` | Heutige Öffnungszeiten mit optionalem Status-Badge | – |
| `[opening_hours_holidays]` | Kommende Feiertage/Ausnahmen | `days` (30), `limit`, `title`, `title_tag` |
| `[opening_hours_compact]` | Kompakte Liste für Footer/Sidebar, gleiche Tage gruppiert | `status`, `title`, `title_tag` |

Legacy-Aliase: `[oeffnungszeiten]`, `[oeffnungszeiten_heute]`, `[oeffnungszeiten_spezial]` (inkl. `tage`-Attribut), `[oeffnungszeiten_kompakt]`.

---

## Systemanforderungen

| Komponente | Minimum |
|---|---|
| WordPress | 6.6 |
| PHP | 7.4 |

---

## Installation

1. Den Ordner nach `wp-content/plugins/` hochladen oder über den Plugin-Bildschirm installieren.
2. Das Plugin aktivieren.
3. Öffnungszeiten unter dem Menüpunkt **Opening Hours** konfigurieren.
4. Shortcodes in Seiten, Beiträge oder Widgets einfügen.

---

## Für Entwickler

Die schema.org-Ausgabe lässt sich per `ohhol_schema_data`-Filter erweitern:

```php
add_filter('ohhol_schema_data', function ($schema) {
    $schema['@type']     = 'Restaurant';
    $schema['telephone'] = '+41 ...';
    return $schema;
});
```

---

## Lizenz

GPL-2.0-or-later – siehe [gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).

**Autor:** Pulacha Labs — [@simurech](https://github.com/simurech)
