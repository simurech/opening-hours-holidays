(function () {
    const data = (typeof ohholData !== 'undefined') ? ohholData : {};
    const i18n = data.i18n || {};
    const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    const weekdayMap = {
        'Sunday': 'sunday', 'Monday': 'monday', 'Tuesday': 'tuesday',
        'Wednesday': 'wednesday', 'Thursday': 'thursday',
        'Friday': 'friday', 'Saturday': 'saturday'
    };

    function getNow() {
        const now = new Date();
        const tz  = data.timezone || null;

        // Intl API: correct business-local time, regardless of the visitor's location.
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
                    dayKey: weekdayMap[wd] || 'monday'
                };
            } catch (e) { /* fall through */ }
        }

        // Fallback: UTC offset from PHP (correct at cache time, ±1h around DST changes).
        const pad    = n => String(n).padStart(2, '0');
        const offset = parseInt(data.utc_offset, 10) || 0;
        const d      = new Date(now.getTime() + offset * 60000);

        return {
            date:   d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate()),
            time:   pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes()),
            dayKey: days[d.getUTCDay()]
        };
    }

    function addDays(dateStr, amount) {
        const d = new Date(dateStr + 'T12:00:00Z');
        d.setUTCDate(d.getUTCDate() + amount);
        return d.toISOString().slice(0, 10);
    }

    function dayKeyOfDate(dateStr) {
        return days[new Date(dateStr + 'T12:00:00Z').getUTCDay()];
    }

    function formatHours(open1, close1, open2, close2) {
        if (!open1 || !close1) return '';
        let s = open1 + ' – ' + close1;
        if (open2 && close2) s += ' / ' + open2 + ' – ' + close2;
        return s;
    }

    function inWindow(time, open, close) {
        return open && close && time >= open && time < close;
    }

    function findHoliday(date) {
        return (data.holidays || []).find(e => e.date === date);
    }

    function isOpenNow(now) {
        const holiday = findHoliday(now.date);
        if (holiday) {
            if (holiday.status === 'closed') return false;
            return inWindow(now.time, holiday.open1, holiday.close1) ||
                   inWindow(now.time, holiday.open2, holiday.close2);
        }

        const day = (data.regular || {})[now.dayKey];
        if (!day || !parseInt(day.open, 10)) return false;
        return inWindow(now.time, day.open1, day.close1) ||
               inWindow(now.time, day.open2, day.close2);
    }

    // Build "<hours or Closed> (note)" content into an element.
    function appendHoursContent(el, hoursText, note) {
        if (hoursText) {
            el.appendChild(document.createTextNode(hoursText));
        } else {
            const closed = document.createElement('span');
            closed.className = 'ohhol-closed';
            closed.textContent = i18n.closed || 'Closed';
            el.appendChild(closed);
        }
        if (note) {
            el.appendChild(document.createTextNode(' '));
            const noteEl = document.createElement('span');
            noteEl.className = 'ohhol-note-inline';
            noteEl.textContent = '(' + note + ')';
            el.appendChild(noteEl);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const now = getNow();

        // Weekly table: mark today's row.
        document.querySelectorAll('.ohhol-week-table tr[data-day]').forEach(row => {
            if (row.dataset.day === now.dayKey) {
                row.classList.add('ohhol-today-row');
                row.setAttribute('aria-current', 'date');
                const dayName = row.querySelector('.ohhol-day-name');
                if (dayName) {
                    const marker = document.createElement('span');
                    marker.className = 'ohhol-today-marker';
                    marker.textContent = i18n.today || 'Today';
                    dayName.appendChild(marker);
                }
            }
        });

        // Weekly table: overlay holiday exceptions within the next 7 days.
        (data.holidays || []).forEach(holiday => {
            const diff = (Date.parse(holiday.date + 'T12:00:00Z') - Date.parse(now.date + 'T12:00:00Z')) / 86400000;
            if (diff < 0 || diff >= 7) return;

            document.querySelectorAll('.ohhol-week-table tr[data-day="' + dayKeyOfDate(holiday.date) + '"]').forEach(row => {
                const cell = row.querySelector('.ohhol-day-hours');
                if (!cell) return;

                const regularText = cell.textContent.trim();
                cell.innerHTML = '';
                row.classList.add('ohhol-has-holiday');

                const holidayLine = document.createElement('div');
                holidayLine.className = 'ohhol-holiday-line';
                const hoursText = holiday.status === 'closed'
                    ? ''
                    : formatHours(holiday.open1, holiday.close1, holiday.open2, holiday.close2);
                appendHoursContent(holidayLine, hoursText, holiday.note || '');
                cell.appendChild(holidayLine);

                const regularLine = document.createElement('div');
                regularLine.className = 'ohhol-regular-line';
                regularLine.textContent = (i18n.regular || 'Regular:') + ' ' + regularText;
                cell.appendChild(regularLine);
            });
        });

        // [opening_hours_today]: built entirely client-side — never shows a stale day.
        document.querySelectorAll('[data-ohhol-today]').forEach(container => {
            const weekdays = data.weekdays || {};
            const dayName  = weekdays[now.dayKey] || now.dayKey;
            const holiday  = findHoliday(now.date);

            let hoursText = '', note = '';
            if (holiday) {
                hoursText = holiday.status === 'closed'
                    ? ''
                    : formatHours(holiday.open1, holiday.close1, holiday.open2, holiday.close2);
                note = holiday.note || '';
            } else {
                const day = (data.regular || {})[now.dayKey];
                if (day && parseInt(day.open, 10)) {
                    hoursText = formatHours(day.open1, day.close1, day.open2, day.close2);
                }
            }

            // Keep the status badge, rebuild the rest.
            const statusBadge = container.querySelector('[data-ohhol-status]');
            container.innerHTML = '';

            const dayEl = document.createElement('span');
            dayEl.className = 'ohhol-today-day';
            dayEl.textContent = dayName + ':';
            container.appendChild(dayEl);
            container.appendChild(document.createTextNode(' '));

            const hoursEl = document.createElement('span');
            hoursEl.className = 'ohhol-today-hours';
            if (!hoursText) {
                hoursEl.classList.add('ohhol-closed');
                hoursEl.textContent = i18n.closed || 'Closed';
            } else {
                hoursEl.textContent = hoursText;
            }
            container.appendChild(hoursEl);

            if (note) {
                container.appendChild(document.createTextNode(' '));
                const noteEl = document.createElement('span');
                noteEl.className = 'ohhol-today-note';
                noteEl.textContent = '(' + note + ')';
                container.appendChild(noteEl);
            }

            if (statusBadge) {
                container.appendChild(document.createTextNode(' '));
                container.appendChild(statusBadge);
            }
        });

        // [opening_hours_holidays]: filter entries by the visitor's real date.
        document.querySelectorAll('[data-ohhol-holidays]').forEach(container => {
            const daysWindow = parseInt(container.dataset.days || '30', 10);
            const limit      = parseInt(container.dataset.limit || '-1', 10);
            const maxDate    = addDays(now.date, daysWindow);

            let visible = 0;
            container.querySelectorAll('tbody tr[data-date]').forEach(row => {
                const d    = row.dataset.date;
                const hide = d < now.date || d > maxDate || (limit > 0 && visible >= limit);
                if (hide) {
                    row.hidden = true;
                } else {
                    visible++;
                }
            });

            if (visible === 0) container.hidden = true;
        });

        // Today's holiday note in the compact status line.
        const todayHoliday = findHoliday(now.date);
        document.querySelectorAll('[data-ohhol-today-note]').forEach(el => {
            if (todayHoliday && todayHoliday.note) {
                el.textContent = '(' + todayHoliday.note + ')';
                el.hidden = false;
            }
        });

        // Status badges.
        const open = isOpenNow(now);
        document.querySelectorAll('[data-ohhol-status]').forEach(badge => {
            badge.textContent = open ? (i18n.openNow || 'Open now') : (i18n.closedNow || 'Currently closed');
            badge.classList.remove('ohhol-open', 'ohhol-shut');
            badge.classList.add(open ? 'ohhol-open' : 'ohhol-shut');
        });
    });
})();
