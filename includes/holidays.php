<?php
/**
 * Holidays & exceptions: date ranges and yearly recurring entries.
 *
 * A stored entry looks like:
 * [
 *   'date'      => '2026-07-27',  // first day (Y-m-d)
 *   'date_end'  => '2026-08-09',  // last day, '' for a single day
 *   'recurring' => 0,             // 1 = repeats every year on the same dates
 *   'status'    => 'closed',      // 'closed' | 'modified'
 *   'open1' => '', 'close1' => '', 'open2' => '', 'close2' => '',
 *   'note'      => 'Company holidays',
 * ]
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Maximum length of a date range in days.
 */
const OHHOL_MAX_RANGE_DAYS = 366;

/**
 * Defaults for a holiday entry (also fills in fields missing in data saved by older versions).
 */
function ohhol_get_holiday_defaults(): array {
    return [
        'date'      => '',
        'date_end'  => '',
        'recurring' => 0,
        'status'    => 'closed',
        'open1'     => '',
        'close1'    => '',
        'open2'     => '',
        'close2'    => '',
        'note'      => '',
    ];
}

/**
 * Today's date (Y-m-d) in the site's time zone.
 */
function ohhol_today(): string {
    return current_time('Y-m-d');
}

/**
 * Add days to a Y-m-d date.
 */
function ohhol_add_days(string $date, int $days): string {
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
    if (!$dt) {
        return $date;
    }
    return $dt->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
}

/**
 * Number of days from $from to $to (Y-m-d).
 */
function ohhol_days_between(string $from, string $to): int {
    $a = DateTimeImmutable::createFromFormat('!Y-m-d', $from, new DateTimeZone('UTC'));
    $b = DateTimeImmutable::createFromFormat('!Y-m-d', $to, new DateTimeZone('UTC'));
    if (!$a || !$b) {
        return 0;
    }
    return (int) round(($b->getTimestamp() - $a->getTimestamp()) / DAY_IN_SECONDS);
}

/**
 * Concrete occurrences of all holiday entries overlapping [$from, $to].
 *
 * @return array<int, array{start: string, end: string, entry: array}> Sorted by start date.
 */
function ohhol_get_holiday_occurrences(string $from, string $to): array {
    $occurrences = [];

    foreach (ohhol_get_holidays() as $index => $entry) {
        $start = $entry['date'];
        $end   = ($entry['date_end'] !== '' && $entry['date_end'] >= $start) ? $entry['date_end'] : $start;

        if (empty($entry['recurring'])) {
            if ($end >= $from && $start <= $to) {
                $occurrences[] = ['start' => $start, 'end' => $end, 'entry' => $entry, 'order' => $index];
            }
            continue;
        }

        // Yearly: same month/day every year from the first year on.
        $span       = ohhol_days_between($start, $end);
        $first_year = (int) substr($start, 0, 4);
        $from_year  = max($first_year, (int) substr($from, 0, 4) - 1);
        $to_year    = (int) substr($to, 0, 4);

        for ($year = $from_year; $year <= $to_year; $year++) {
            $month = (int) substr($start, 5, 2);
            $day   = (int) substr($start, 8, 2);
            if (!checkdate($month, $day, $year)) {
                // 29 February in a non-leap year.
                $day = 28;
            }
            $occ_start = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $occ_end   = ohhol_add_days($occ_start, $span);
            if ($occ_end >= $from && $occ_start <= $to) {
                $occurrences[] = ['start' => $occ_start, 'end' => $occ_end, 'entry' => $entry, 'order' => $index];
            }
        }
    }

    usort($occurrences, static function ($a, $b) {
        return [$a['start'], $a['order']] <=> [$b['start'], $b['order']];
    });

    return $occurrences;
}

/**
 * Holiday entry per day for [$from, $to], keyed by Y-m-d.
 *
 * One-off entries take precedence over yearly ones; otherwise later entries win.
 */
function ohhol_get_holiday_days(string $from, string $to): array {
    $occurrences = ohhol_get_holiday_occurrences($from, $to);

    // Yearly entries first, so one-off entries override them.
    usort($occurrences, static function ($a, $b) {
        return [(int) !empty($b['entry']['recurring']), $a['order']] <=> [(int) !empty($a['entry']['recurring']), $b['order']];
    });

    $days = [];
    foreach ($occurrences as $occ) {
        $day = max($occ['start'], $from);
        $end = min($occ['end'], $to);
        while ($day <= $end) {
            $days[$day] = ['date' => $day] + $occ['entry'];
            $day        = ohhol_add_days($day, 1);
        }
    }
    ksort($days);
    return $days;
}

/**
 * Get the holiday entry that applies to a specific date (Y-m-d) or null.
 */
function ohhol_get_holiday_for_date(string $date): ?array {
    static $cache = [];
    if (!array_key_exists($date, $cache)) {
        $days           = ohhol_get_holiday_days($date, $date);
        $cache[$date]   = $days[$date] ?? null;
    }
    return $cache[$date];
}

/**
 * Time windows [open, close] of a regular day or holiday entry.
 */
function ohhol_get_windows(array $source): array {
    $windows = [];
    foreach ([1, 2] as $n) {
        if (!empty($source['open' . $n]) && !empty($source['close' . $n])) {
            $windows[] = [$source['open' . $n], $source['close' . $n]];
        }
    }
    return $windows;
}

/**
 * Time windows starting on a given date (holiday exception first, otherwise the weekday).
 */
function ohhol_get_windows_for_date(string $date): array {
    $holiday = ohhol_get_holiday_for_date($date);
    if ($holiday) {
        return $holiday['status'] === 'closed' ? [] : ohhol_get_windows($holiday);
    }
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
    if (!$dt) {
        return [];
    }
    $day = ohhol_get_hours()[strtolower($dt->format('l'))] ?? null;
    return ($day && !empty($day['open'])) ? ohhol_get_windows($day) : [];
}

/**
 * Current open state, computed server-side (used for previews in the block editor;
 * the frontend computes the same in JavaScript so cached pages stay correct).
 *
 * @return array{open: bool, closes_in: ?int, opens_in: ?int} Minutes until the next change.
 */
function ohhol_get_current_status(?DateTimeImmutable $now = null): array {
    $now   = $now ?: new DateTimeImmutable('now', wp_timezone());
    $today = $now->format('Y-m-d');
    $t     = (int) $now->format('G') * 60 + (int) $now->format('i');

    $to_min    = static function (string $hm): int {
        return (int) substr($hm, 0, 2) * 60 + (int) substr($hm, 3, 2);
    };
    $intervals = [];
    foreach ([-1, 0, 1] as $offset) {
        foreach (ohhol_get_windows_for_date(ohhol_add_days($today, $offset)) as $w) {
            $open  = $to_min($w[0]) + $offset * 1440;
            $close = $to_min($w[1]) + $offset * 1440;
            if ($close <= $open) {
                $close += 1440;
            }
            $intervals[] = [$open, $close];
        }
    }

    $status = ['open' => false, 'closes_in' => null, 'opens_in' => null];
    foreach ($intervals as [$open, $close]) {
        if ($t >= $open && $t < $close) {
            $status['open']      = true;
            $status['closes_in'] = max($status['closes_in'] ?? 0, $close - $t);
        } elseif ($open > $t) {
            $status['opens_in'] = min($status['opens_in'] ?? PHP_INT_MAX, $open - $t);
        }
    }
    return $status;
}
