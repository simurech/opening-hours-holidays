# Öffnungszeiten Plugin

WordPress-Plugin zur Verwaltung von regulären und speziellen Öffnungszeiten mit Shortcodes.

## Features

- Reguläre Öffnungszeiten pro Wochentag (bis zu zwei Zeitfenster pro Tag)
- Spezielle Öffnungszeiten für Feiertage, Betriebsferien und Ausnahmen
- Vier Shortcodes für verschiedene Darstellungsformen
- „Jetzt geöffnet / Aktuell geschlossen"-Badge
- Kompatibel mit Full-Page-Caching (LiteSpeed, WP Rocket u.a.)
- Korrekte Zeitzonenbehandlung – auch für internationale Besucher
- Anpassbares Design (Farben, Zebra-Streifen, Badges) über den Admin-Bereich

## Shortcodes

### Wochenübersicht

```
[oeffnungszeiten]
[oeffnungszeiten title="Unsere Öffnungszeiten" title_tag="h2"]
```

Zeigt alle Wochentage als Tabelle. Der heutige Tag wird hervorgehoben.

### Heutiger Tag

```
[oeffnungszeiten_heute]
```

Zeigt den aktuellen Wochentag mit Öffnungszeiten und optionalem Status-Badge.

### Spezielle Öffnungszeiten

```
[oeffnungszeiten_spezial]
[oeffnungszeiten_spezial tage="60" limit="5" title="Feiertage"]
```

Zeigt kommende Sondertage (Feiertage, Betriebsferien etc.).

| Parameter | Standard | Beschreibung |
|-----------|----------|--------------|
| `tage` | `30` | Vorschau in Tagen |
| `limit` | unbegrenzt | Max. Anzahl Einträge |
| `title` | – | Optionaler Titel |
| `title_tag` | `h2` | HTML-Tag für den Titel |

### Kompakte Übersicht

```
[oeffnungszeiten_kompakt]
[oeffnungszeiten_kompakt title="Öffnungszeiten" status="1"]
```

Platzsparende Liste für Footer oder Sidebar. Tage mit gleichen Zeiten werden automatisch zusammengefasst (z.B. „Montag – Freitag").

| Parameter | Standard | Beschreibung |
|-----------|----------|--------------|
| `status` | `0` | Status-Badge anzeigen (`1` = ja) |
| `title` | – | Optionaler Titel |
| `title_tag` | `h3` | HTML-Tag für den Titel |

## Installation

1. Plugin-Ordner nach `/wp-content/plugins/oeffnungszeiten-plugin/` hochladen
2. Plugin im WordPress-Admin unter **Plugins** aktivieren
3. Öffnungszeiten unter dem Menüpunkt **Öffnungszeiten** konfigurieren
4. Shortcodes in Seiten, Beiträgen oder Widgets einbinden

## Voraussetzungen

- WordPress 5.8+
- PHP 7.4+
