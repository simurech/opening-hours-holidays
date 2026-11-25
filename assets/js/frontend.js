/**
 * Opening Hours & Holidays – frontend.
 *
 * Everything that depends on the current date/time is rendered here so that
 * full-page caching never shows stale information. The output is refreshed
 * every minute while the page stays open.
 */
(function () {
    'use strict';

    const data = (typeof window.ohholData !== 'undefined') ? window.ohholData : {};
    const i18n = data.i18n || {};
    const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    const weekdayMap = {
        'Sunday': 'sunday', 'Monday': 'monday', 'Tuesday': 'tuesday',
        'Wednesday': 'wednesday', 'Thursday': 'thursday',
        'Friday': 'friday', 'Saturday': 'saturday'
    };

    // Original server-rendered content of weekly table cells (restored before each render).
    const originalCells = new WeakMap();

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

    function findHoliday(date) {
        return (data.holidays || []).find(e => e.date === date);
    }

    /**
     * Time windows [open, close] that start on the given date
     * (holiday exception first, otherwise the regular weekday).
     */
    function windowsFor(date) {
        const pairs   = src => [[src.open1, src.close1], [src.open2, src.close2]].filter(w => w[0] && w[1]);
        const holiday = findHoliday(date);
        if (holiday) {
            return holiday.status === 'closed' ? [] : pairs(holiday);
        }
        const day = (data.regular || {})[dayKeyOfDate(date)];
        return (day && parseInt(day.open, 10)) ? pairs(day) : [];
    }

    function toMinutes(hm) {
        return parseInt(hm.slice(0, 2), 10) * 60 + parseInt(hm.slice(3, 5), 10);
    }

    /**
     * Open state and minutes until the next change.
     * Windows of yesterday, today and tomorrow are turned into minute intervals
     * relative to today 00:00; a closing time <= opening time runs past midnight.
     */
    function getStatus(now) {
        const t = toMinutes(now.time);
        const status = { open: false, closesIn: null, opensIn: null };

        [-1, 0, 1].forEach(offset => {
            windowsFor(addDays(now.date, offset)).forEach(([o, c]) => {
                const open = toMinutes(o) + offset * 1440;
                let close  = toMinutes(c) + offset * 1440;
                if (close <= open) close += 1440;

                if (t >= open && t < close) {
                    status.open = true;
                    status.closesIn = Math.max(status.closesIn || 0, close - t);
                } else if (open > t) {
                    status.opensIn = status.opensIn === null ? open - t : Math.min(status.opensIn, open - t);
                }
            });
        });

        return status;
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

    function renderWeek(now) {
        document.querySelectorAll('.ohhol-week-table tr[data-day]').forEach(row => {
            const cell = row.querySelector('.ohhol-day-hours');
            if (!cell) return;

            // Reset to the server-rendered state.
            if (!originalCells.has(cell)) {
                originalCells.set(cell, Array.from(cell.childNodes).map(n => n.cloneNode(true)));
            }
            cell.replaceChildren(...originalCells.get(cell).map(n => n.cloneNode(true)));
            row.classList.remove('ohhol-today-row', 'ohhol-has-holiday');
            row.removeAttribute('aria-current');
            row.querySelectorAll('.ohhol-today-marker').forEach(m => m.remove());

            // Today marker.
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

        // Overlay holiday exceptions within the next 7 days.
        (data.holidays || []).forEach(holiday => {
            const diff = (Date.parse(holiday.date + 'T12:00:00Z') - Date.parse(now.date + 'T12:00:00Z')) / 86400000;
            if (diff < 0 || diff >= 7) return;

            document.querySelectorAll('.ohhol-week-table tr[data-day="' + dayKeyOfDate(holiday.date) + '"]').forEach(row => {
                const cell = row.querySelector('.ohhol-day-hours');
                if (!cell) return;

                const regularText = cell.textContent.trim();
                cell.replaceChildren();
                row.classList.add('ohhol-has-holiday');

                const holidayLine = document.createElement('div');
                holidayLine.className = 'ohhol-holiday-line';
                appendHoursContent(holidayLine, holiday.status === 'closed' ? '' : (holiday.text || ''), holiday.note || '');
                cell.appendChild(holidayLine);

                const regularLine = document.createElement('div');
                regularLine.className = 'ohhol-regular-line';
                regularLine.textContent = (i18n.regular || 'Regular:') + ' ' + regularText;
                cell.appendChild(regularLine);
            });
        });
    }

    function renderToday(now) {
        document.querySelectorAll('[data-ohhol-today]').forEach(container => {
            const weekdays = data.weekdays || {};
            const dayName  = weekdays[now.dayKey] || now.dayKey;
            const holiday  = findHoliday(now.date);

            let hoursText = '', note = '';
            if (holiday) {
                hoursText = holiday.status === 'closed' ? '' : (holiday.text || '');
                note = holiday.note || '';
            } else {
                const day = (data.regular || {})[now.dayKey];
                if (day && parseInt(day.open, 10)) {
                    hoursText = day.text || '';
                }
            }

            // Keep the status badge, rebuild the rest.
            const statusBadge = container.querySelector('[data-ohhol-status]');
            container.replaceChildren();

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
    }

    function renderHolidayLists(now) {
        document.querySelectorAll('[data-ohhol-holidays]').forEach(container => {
            const daysWindow = parseInt(container.dataset.days || '30', 10);
            const limit      = parseInt(container.dataset.limit || '-1', 10);
            const maxDate    = addDays(now.date, daysWindow);

            let visible = 0;
            container.querySelectorAll('tbody tr[data-date]').forEach(row => {
                const start = row.dataset.date;
                const end   = row.dataset.end || start;
                const hide  = end < now.date || start > maxDate || (limit > 0 && visible >= limit);
                row.hidden = hide;
                if (!hide) visible++;
            });

            container.hidden = (visible === 0);
        });
    }

    function renderStatus(now) {
        const todayHoliday = findHoliday(now.date);
        document.querySelectorAll('[data-ohhol-today-note]').forEach(el => {
            const note = todayHoliday && todayHoliday.note ? todayHoliday.note : '';
            el.textContent = note ? '(' + note + ')' : '';
            el.hidden = !note;
        });

        const status = getStatus(now);
        const open   = status.open;
        const soon   = parseInt(data.soon, 10) || 0;
        const isSoon = soon > 0 && (open
            ? status.closesIn !== null && status.closesIn <= soon
            : status.opensIn !== null && status.opensIn <= soon);

        let text;
        if (open) {
            text = isSoon ? (i18n.closesSoon || 'Closes soon') : (i18n.openNow || 'Open now');
        } else {
            text = isSoon ? (i18n.opensSoon || 'Opens soon') : (i18n.closedNow || 'Currently closed');
        }

        document.querySelectorAll('[data-ohhol-status]').forEach(badge => {
            // Only touch the live region when something changed (avoids repeated announcements).
            if (badge.textContent !== text) {
                badge.textContent = text;
            }
            badge.classList.toggle('ohhol-open', open);
            badge.classList.toggle('ohhol-shut', !open);
            badge.classList.toggle('ohhol-soon', isSoon);
        });

        // [opening_hours_if_open] / [opening_hours_if_closed]
        document.querySelectorAll('[data-ohhol-if]').forEach(el => {
            el.hidden = (el.dataset.ohholIf === 'open') !== open;
        });
    }

    let lastDate = null;

    function render() {
        const now = getNow();
        if (now.date !== lastDate) {
            lastDate = now.date;
            renderWeek(now);
            renderToday(now);
            renderHolidayLists(now);
        }
        renderStatus(now);
    }

    function init() {
        render();
        // Refresh at the start of every minute.
        setTimeout(() => {
            render();
            setInterval(render, 60000);
        }, (60 - new Date().getSeconds()) * 1000 + 50);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
