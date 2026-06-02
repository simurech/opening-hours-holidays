(function () {
    const dagKeys = ['sonntag', 'montag', 'dienstag', 'mittwoch', 'donnerstag', 'freitag', 'samstag'];

    function getNow() {
        const d = new Date();
        const pad = n => String(n).padStart(2, '0');
        return {
            date:   `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`,
            time:   `${pad(d.getHours())}:${pad(d.getMinutes())}`,
            dayKey: dagKeys[d.getDay()]
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
