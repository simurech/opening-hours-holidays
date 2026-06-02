(function () {
    const weekdayMap = {
        'Sunday': 'sonntag', 'Monday': 'montag', 'Tuesday': 'dienstag',
        'Wednesday': 'mittwoch', 'Thursday': 'donnerstag',
        'Friday': 'freitag', 'Saturday': 'samstag'
    };

    function getNow() {
        const now = new Date();
        const tz  = (typeof ozData !== 'undefined' && ozData.timezone) ? ozData.timezone : null;

        // Intl API: korrekte Geschäfts-Ortszeit unabhängig vom Standort des Besuchers
        if (tz && typeof Intl !== 'undefined' && Intl.DateTimeFormat) {
            try {
                const fmtParts = (opts) =>
                    new Intl.DateTimeFormat('en', Object.assign({ timeZone: tz }, opts)).formatToParts(now);
                const get = (parts, type) => (parts.find(p => p.type === type) || {}).value || '';

                const dp   = fmtParts({ year: 'numeric', month: '2-digit', day: '2-digit' });
                const tp   = fmtParts({ hour: '2-digit', minute: '2-digit', hour12: false });
                const hour = get(tp, 'hour') === '24' ? '00' : get(tp, 'hour');
                const wd   = new Intl.DateTimeFormat('en', { timeZone: tz, weekday: 'long' }).format(now);

                return {
                    date:   get(dp, 'year') + '-' + get(dp, 'month') + '-' + get(dp, 'day'),
                    time:   hour + ':' + get(tp, 'minute'),
                    dayKey: weekdayMap[wd] || 'montag'
                };
            } catch (e) { /* Fallback */ }
        }

        // Fallback: UTC-Offset aus PHP (korrekt zum Cache-Zeitpunkt, ±1h bei Zeitumstellung möglich)
        const pad    = n => String(n).padStart(2, '0');
        const offset = (typeof ozData !== 'undefined' && ozData.utc_offset) ? parseInt(ozData.utc_offset) : 0;
        const d      = new Date(now.getTime() + offset * 60000);
        const days   = ['sonntag', 'montag', 'dienstag', 'mittwoch', 'donnerstag', 'freitag', 'samstag'];

        return {
            date:   d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate()),
            time:   pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes()),
            dayKey: days[d.getUTCDay()]
        };
    }

    function formatZeiten(von1, bis1, von2, bis2) {
        if (!von1 || !bis1) return 'Geschlossen';
        let s = von1 + ' – ' + bis1;
        if (von2 && bis2) s += ' / ' + von2 + ' – ' + bis2;
        return s;
    }

    function zeitInFenster(jetzt, von, bis) {
        return von && bis && jetzt >= von && jetzt <= bis;
    }

    function istJetztOffen(now) {
        if (typeof ozData === 'undefined') return false;

        const spezial = (ozData.spezial || []).find(e => e.datum === now.date);
        if (spezial) {
            if (spezial.status === 'geschlossen') return false;
            return zeitInFenster(now.time, spezial.von1, spezial.bis1) ||
                   zeitInFenster(now.time, spezial.von2, spezial.bis2);
        }

        const tag = (ozData.regulaer || {})[now.dayKey];
        if (!tag || !parseInt(tag.offen)) return false;
        return zeitInFenster(now.time, tag.von1, tag.bis1) ||
               zeitInFenster(now.time, tag.von2, tag.bis2);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const now = getNow();

        // Wochenübersicht: heutige Zeile markieren + aria-current
        document.querySelectorAll('.oz-oeffnungszeiten-tabelle tr[data-tag]').forEach(row => {
            if (row.dataset.tag === now.dayKey) {
                row.classList.add('oz-heute');
                row.setAttribute('aria-current', 'date');
                const tagName = row.querySelector('.oz-tag-name');
                if (tagName) {
                    const span = document.createElement('span');
                    span.className = 'oz-heute-marker';
                    span.textContent = 'Heute';
                    tagName.appendChild(span);
                }
            }
        });

        // [oeffnungszeiten_heute]: Wochentag und Zeiten clientseitig aktualisieren
        document.querySelectorAll('[data-oz-heute]').forEach(container => {
            const wochentage = (typeof ozData !== 'undefined' && ozData.wochentage) ? ozData.wochentage : {};
            const dayName    = wochentage[now.dayKey] || now.dayKey;

            const spezial = (ozData.spezial || []).find(e => e.datum === now.date);
            let zeitenText, istGeschlossen = false, bemerkung = '';

            if (spezial) {
                istGeschlossen = spezial.status === 'geschlossen';
                zeitenText     = istGeschlossen
                    ? 'Geschlossen'
                    : formatZeiten(spezial.von1, spezial.bis1, spezial.von2, spezial.bis2);
                bemerkung = spezial.bemerkung || '';
            } else {
                const tag = (ozData.regulaer || {})[now.dayKey];
                if (!tag || !parseInt(tag.offen)) {
                    zeitenText = 'Geschlossen'; istGeschlossen = true;
                } else {
                    zeitenText = formatZeiten(tag.von1, tag.bis1, tag.von2, tag.bis2);
                }
            }

            const tagEl = container.querySelector('.oz-heute-tag');
            if (tagEl) tagEl.textContent = dayName + ':';

            const zeitenEl = container.querySelector('.oz-heute-zeiten');
            if (zeitenEl) {
                zeitenEl.textContent = zeitenText;
                zeitenEl.classList.toggle('oz-geschlossen', istGeschlossen);
            }

            const bemerkungEl = container.querySelector('.oz-heute-bemerkung');
            if (bemerkungEl) bemerkungEl.textContent = bemerkung ? '(' + bemerkung + ')' : '';
        });

        // Status-Badges aktualisieren
        const offen = istJetztOffen(now);
        document.querySelectorAll('[data-oz-status]').forEach(badge => {
            badge.textContent = offen ? 'Jetzt geöffnet' : 'Aktuell geschlossen';
            badge.classList.remove('oz-offen', 'oz-zu');
            badge.classList.add(offen ? 'oz-offen' : 'oz-zu');
        });
    });
})();
