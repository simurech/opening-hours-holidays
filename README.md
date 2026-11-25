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
- Feiertage & Ausnahmen: einzelne Tage oder **Zeiträume** (z. B. Betriebsferien), optional **jährlich wiederkehrend**
- **CSV-Import & -Export** für Feiertage, inkl. Vorlage (Spalte A = Datum, Spalte B = Bezeichnung) und Vorschau vor dem Import
- **Block „Opening Hours“** für den Block-Editor mit Live-Vorschau, dazu fünf Shortcodes
- „Jetzt geöffnet / Aktuell geschlossen"-Badge mit Hinweis **„Öffnet bald / Schliesst bald“**, aktualisiert sich live
- **Bedingte Inhalte**: Text oder Buttons nur während der Öffnungszeiten (oder nur ausserhalb) anzeigen
- Kompatibel mit Full-Page-Caching (LiteSpeed, WP Rocket etc.)
- Korrekte Zeitzonen-Behandlung — auch für internationale Besucher
- Zeitformat (12/24 h) und Wochenbeginn gemäss WordPress-Einstellungen
- Optionale schema.org-Ausgabe (JSON-LD) mit Business-Typ, Adresse, Telefon und Feiertagszeiten
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
| `[opening_hours_if_open]…[/opening_hours_if_open]` | Inhalt nur während der Öffnungszeiten | `inline` |
| `[opening_hours_if_closed]…[/opening_hours_if_closed]` | Inhalt nur ausserhalb der Öffnungszeiten | `inline` |

Alle Ansichten gibt es auch als Block **„Opening Hours“**.

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

Business-Typ, Adresse und Telefon werden in den Einstellungen gepflegt. Für alles Weitere gibt es Filter:

| Filter | Zweck |
|---|---|
| `ohhol_schema_data` | schema.org-Array anpassen, z. B. Geo-Koordinaten oder Logo ergänzen |
| `ohhol_output_schema` | `false` zurückgeben, um JSON-LD auf bestimmten Seiten auszulassen |
| `ohhol_schema_conflicts` | Erkennung anderer Plugins mit LocalBusiness-Ausgabe anpassen |
| `ohhol_time_format` | PHP-Datumsformat für die Anzeige der Zeiten ändern |

```php
add_filter('ohhol_schema_data', function ($schema) {
    $schema['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => 46.948, 'longitude' => 7.447];
    return $schema;
});
```

### Entwicklung

```bash
composer install     # PHPCS mit WordPress- und PHP-7.4-Kompatibilitätsregeln
vendor/bin/phpcs
```

Die CI prüft bei jedem Push: PHP-Lint (7.4 – 8.5), PHPCS, Plugin Check und konsistente Versionsnummern. Ein Version-Bump auf `main` erzeugt automatisch ein GitHub-Release mit Plugin-ZIP. Die Grafiken für WordPress.org liegen in `.wordpress-org/`.

---

## Lizenz

GPL-2.0-or-later – siehe [gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).

**Autor:** Pulacha Labs — [@simurech](https://github.com/simurech)
