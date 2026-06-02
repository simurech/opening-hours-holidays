(function () {
    const weekdayMap = {
        'Sunday': 'sonntag', 'Monday': 'montag', 'Tuesday': 'dienstag',
        'Wednesday': 'mittwoch', 'Thursday': 'donnerstag',
        'Friday': 'freitag', 'Saturday': 'samstag'
    };

    function getNow() {
        const now = new Date();
        const tz  = (typeof ozData !== 'undefined' && ozData.timezone) ? ozData.timezone : null;

        // Intl API: gibt korrekte Geschäfts-Ortszeit zurück, unabhängig vom Standort des Besuchers
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

        // Fallback: UTC-Offset aus PHP (korrekt zum Zeitpunkt des Cache-Aufbaus, kann bei
        // Zeitumstellung um ±1h abweichen – tritt höchstens zweimal jährlich auf)
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

        // "Heute"-Zeile in der Wochenübersicht markieren
        document.querySelectorAll('.oz-oeffnungszeiten-tabelle tr[data-tag]').forEach(row => {
            if (row.dataset.tag === now.dayKey) {
                row.classList.add('oz-heute');
                const tagName = row.querySelector('.oz-tag-name');
                if (tagName) {
                    const span = document.createElement('span');
                    span.className = 'oz-heute-marker';
                    span.textContent = 'Heute';
                    tagName.appendChild(span);
                }
            }
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
