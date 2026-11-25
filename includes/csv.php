<?php
/**
 * CSV import/export for holidays & exceptions.
 *
 * Column layout (only A is required, header row optional):
 *   A = date (2026-12-25 or 25.12.2026)   B = title / note
 *   C = to date (range)   D = status (closed/modified)   E–H = from 1, to 1, from 2, to 2
 *   I = every year (yes/no)
 * The template only contains columns A and B; the export writes all columns
 * so it can be imported again without losing anything.
 */

if (!defined('ABSPATH')) {
    exit;
}

const OHHOL_CSV_MAX_BYTES = 1048576;
const OHHOL_CSV_MAX_ROWS  = 1000;

/**
 * URL of the settings page, optionally with the CSV section anchor.
 */
function ohhol_settings_url(array $args = []): string {
    return add_query_arg(array_merge(['page' => 'ohhol-settings'], $args), admin_url('admin.php')) . '#ohhol-csv';
}

/**
 * Transient key for per-user CSV data.
 */
function ohhol_csv_transient(string $type): string {
    return 'ohhol_csv_' . $type . '_' . get_current_user_id();
}

/**
 * Abort unless the current user may manage the plugin.
 */
function ohhol_csv_check_capability(): void {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Sorry, you are not allowed to do that.', 'opening-hours-holidays'), 403);
    }
}

/**
 * Date format used in CSV files (German-speaking sites get 25.12.2026).
 */
function ohhol_csv_date_format(): string {
    return strpos(get_locale(), 'de') === 0 ? 'd.m.Y' : 'Y-m-d';
}

/**
 * Format a Y-m-d date for CSV output.
 */
function ohhol_csv_format_date(string $date): string {
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $dt ? $dt->format(ohhol_csv_date_format()) : $date;
}

/**
 * Parse a date in one of the accepted formats; returns Y-m-d or ''.
 */
function ohhol_csv_parse_date(string $value): string {
    $value = trim($value);
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m)) {
        [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
    } elseif (preg_match('#^(\d{1,2})[./-](\d{1,2})[./-](\d{2}|\d{4})$#', $value, $m)) {
        [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if ($y < 100) {
            $y += 2000;
        }
    } else {
        return '';
    }
    return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : '';
}

/**
 * Parse a time like 9:00, 09.30 or 0930; returns HH:MM or ''.
 */
function ohhol_csv_parse_time(string $value): string {
    $value = trim($value);
    if (preg_match('/^(\d{1,2})[:.]?(\d{2})$/', $value, $m)) {
        return ohhol_sanitize_time($m[1] . ':' . $m[2]);
    }
    return '';
}

/**
 * Accepted words for status and yes/no columns (English + translated).
 */
function ohhol_csv_words(): array {
    return [
        'closed'   => ['closed', 'geschlossen', 'zu', strtolower(_x('closed', 'CSV status value', 'opening-hours-holidays'))],
        'modified' => ['modified', 'modified hours', 'geändert', 'geaendert', 'angepasst', 'offen', 'open', strtolower(_x('modified', 'CSV status value', 'opening-hours-holidays'))],
        'yes'      => ['1', 'x', 'yes', 'y', 'true', 'ja', 'j', 'jährlich', 'jaehrlich', strtolower(_x('yes', 'CSV yes value', 'opening-hours-holidays'))],
    ];
}

/**
 * Convert uploaded CSV content to UTF-8 (Excel often saves Windows-1252).
 */
function ohhol_csv_to_utf8(string $content): string {
    if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
        $content = substr($content, 3);
    }
    $is_utf8 = function_exists('wp_is_valid_utf8')
        ? wp_is_valid_utf8($content)
        : seems_utf8($content); // phpcs:ignore WordPress.WP.DeprecatedFunctions.seems_utf8Found -- fallback for WordPress < 6.9.
    if ($is_utf8) {
        return $content;
    }
    if (function_exists('mb_convert_encoding')) {
        return (string) mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
    }
    if (function_exists('iconv')) {
        $converted = iconv('Windows-1252', 'UTF-8//IGNORE', $content);
        return $converted === false ? '' : $converted;
    }
    return wp_check_invalid_utf8($content, true);
}

/**
 * Parse CSV content into holiday entries.
 *
 * @return array{rows: array, skipped: array} Valid entries and skipped lines (line, content, reason).
 */
function ohhol_parse_holiday_csv(string $content): array {
    $content = str_replace(["\r\n", "\r"], "\n", ohhol_csv_to_utf8($content));
    $result  = ['rows' => [], 'skipped' => []];

    // Detect the delimiter from the first non-empty line.
    $first = '';
    foreach (explode("\n", $content) as $line) {
        if (trim($line) !== '') {
            $first = $line;
            break;
        }
    }
    $delimiter = ';';
    $best      = 0;
    foreach ([';', ',', "\t"] as $candidate) {
        $count = substr_count($first, $candidate);
        if ($count > $best) {
            $best      = $count;
            $delimiter = $candidate;
        }
    }

    $words  = ohhol_csv_words();
    $cutoff = wp_date('Y-m-d', time() - 90 * DAY_IN_SECONDS);
    $line   = 0;

    foreach (ohhol_csv_split_records($content) as $record) {
        $line++;
        $cols = array_map(static function ($v) {
            return trim((string) $v);
        }, str_getcsv($record, $delimiter, '"', ''));

        if (implode('', $cols) === '') {
            continue;
        }
        if (count($result['rows']) >= OHHOL_CSV_MAX_ROWS) {
            $result['skipped'][] = [$line, implode(' ', $cols), __('Too many rows (maximum 1000)', 'opening-hours-holidays')];
            break;
        }

        $date = ohhol_csv_parse_date($cols[0] ?? '');
        if ($date === '') {
            // A first line without a date is the header row.
            if ($line === 1) {
                continue;
            }
            $result['skipped'][] = [$line, implode(' ', $cols), __('Invalid or missing date in column A', 'opening-hours-holidays')];
            continue;
        }

        $date_end = '';
        if (($cols[2] ?? '') !== '') {
            $date_end = ohhol_csv_parse_date($cols[2]);
            if ($date_end === '') {
                $result['skipped'][] = [$line, implode(' ', $cols), __('Invalid "to" date in column C', 'opening-hours-holidays')];
                continue;
            }
            if ($date_end < $date) {
                $result['skipped'][] = [$line, implode(' ', $cols), __('The "to" date is before the start date', 'opening-hours-holidays')];
                continue;
            }
            if ($date_end === $date) {
                $date_end = '';
            }
        }

        $recurring = in_array(strtolower($cols[8] ?? ''), $words['yes'], true) ? 1 : 0;
        if (!$recurring && ($date_end ?: $date) < $cutoff) {
            $result['skipped'][] = [$line, implode(' ', $cols), __('More than 90 days in the past', 'opening-hours-holidays')];
            continue;
        }

        $status_raw = strtolower($cols[3] ?? '');
        $status     = in_array($status_raw, $words['modified'], true) ? 'modified' : 'closed';
        if ($status_raw !== '' && $status === 'closed' && !in_array($status_raw, $words['closed'], true)) {
            $result['skipped'][] = [$line, implode(' ', $cols), __('Unknown status in column D (use "closed" or "modified")', 'opening-hours-holidays')];
            continue;
        }

        $issue   = false;
        $windows = ohhol_normalize_windows([
            'open1'  => ohhol_csv_parse_time($cols[4] ?? ''),
            'close1' => ohhol_csv_parse_time($cols[5] ?? ''),
            'open2'  => ohhol_csv_parse_time($cols[6] ?? ''),
            'close2' => ohhol_csv_parse_time($cols[7] ?? ''),
        ], $issue);
        if ($status === 'modified' && $windows['open1'] === '') {
            $result['skipped'][] = [$line, implode(' ', $cols), __('Status "modified" needs opening hours in columns E and F', 'opening-hours-holidays')];
            continue;
        }
        if ($status === 'closed') {
            $windows = ['open1' => '', 'close1' => '', 'open2' => '', 'close2' => ''];
        }

        $result['rows'][] = [
            'date'      => $date,
            'date_end'  => $date_end,
            'recurring' => $recurring,
            'status'    => $status,
        ] + $windows + [
            'note'      => sanitize_text_field($cols[1] ?? ''),
        ];
    }

    return $result;
}

/**
 * Split CSV content into records (newlines inside quoted fields are kept).
 */
function ohhol_csv_split_records(string $content): array {
    $records  = [];
    $current  = '';
    $in_quote = false;
    $length   = strlen($content);

    for ($i = 0; $i < $length; $i++) {
        $char = $content[$i];
        if ($char === '"') {
            $in_quote = !$in_quote;
        }
        if ($char === "\n" && !$in_quote) {
            $records[] = $current;
            $current   = '';
            continue;
        }
        $current .= $char;
    }
    if ($current !== '') {
        $records[] = $current;
    }
    return $records;
}

/**
 * Build one CSV line (semicolon-separated, RFC 4180 quoting).
 */
function ohhol_csv_line(array $row): string {
    $cells = array_map(static function ($value) {
        $value = (string) $value;
        if (preg_match('/[;"\r\n]/', $value)) {
            $value = '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }, $row);
    return implode(';', $cells) . "\r\n";
}

/**
 * Send CSV rows as a download.
 */
function ohhol_send_csv(string $filename, array $rows): void {
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');

    // BOM so Excel opens UTF-8 correctly.
    $csv = "\xEF\xBB\xBF";
    foreach ($rows as $row) {
        $csv .= ohhol_csv_line($row);
    }
    echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download (text/csv), not HTML; formula cells are neutralized.
    exit;
}

/**
 * Neutralize values that spreadsheet apps would run as formulas.
 */
function ohhol_csv_safe_cell(string $value): string {
    return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
}

/**
 * Download: template (columns A and B with examples).
 */
function ohhol_handle_csv_template(): void {
    ohhol_csv_check_capability();
    check_admin_referer('ohhol_csv_template');
    $year = (int) wp_date('Y') + 1;
    ohhol_send_csv('opening-hours-holidays-template.csv', [
        [__('Date', 'opening-hours-holidays'), _x('Title', 'CSV column', 'opening-hours-holidays')],
        [ohhol_csv_format_date($year . '-01-01'), __('New Year\'s Day', 'opening-hours-holidays')],
        [ohhol_csv_format_date($year . '-12-25'), __('Christmas Day', 'opening-hours-holidays')],
        [ohhol_csv_format_date($year . '-12-26'), __('Boxing Day', 'opening-hours-holidays')],
    ]);
}
add_action('admin_post_ohhol_csv_template', 'ohhol_handle_csv_template');

/**
 * Download: export of all entries (all columns).
 */
function ohhol_handle_csv_export(): void {
    ohhol_csv_check_capability();
    check_admin_referer('ohhol_csv_export');
    $rows = [[
        __('Date', 'opening-hours-holidays'),
        _x('Title', 'CSV column', 'opening-hours-holidays'),
        __('To', 'opening-hours-holidays'),
        __('Status', 'opening-hours-holidays'),
        __('From 1', 'opening-hours-holidays'),
        __('To 1', 'opening-hours-holidays'),
        __('From 2', 'opening-hours-holidays'),
        __('To 2', 'opening-hours-holidays'),
        __('Every year', 'opening-hours-holidays'),
    ]];
    foreach (ohhol_get_holidays() as $entry) {
        $rows[] = [
            ohhol_csv_format_date($entry['date']),
            ohhol_csv_safe_cell($entry['note']),
            $entry['date_end'] !== '' ? ohhol_csv_format_date($entry['date_end']) : '',
            $entry['status'] === 'modified'
                ? _x('modified', 'CSV status value', 'opening-hours-holidays')
                : _x('closed', 'CSV status value', 'opening-hours-holidays'),
            $entry['open1'],
            $entry['close1'],
            $entry['open2'],
            $entry['close2'],
            $entry['recurring'] ? _x('yes', 'CSV yes value', 'opening-hours-holidays') : '',
        ];
    }
    ohhol_send_csv('opening-hours-holidays-' . wp_date('Y-m-d') . '.csv', $rows);
}
add_action('admin_post_ohhol_csv_export', 'ohhol_handle_csv_export');

/**
 * Upload: parse the file and keep the result for the preview.
 */
function ohhol_handle_csv_preview(): void {
    ohhol_csv_check_capability();
    check_admin_referer('ohhol_csv_preview');

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below, content is parsed and sanitized field by field.
    $file  = $_FILES['ohhol_csv_file'] ?? null;
    $error = '';

    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $error = __('Please choose a CSV file to upload.', 'opening-hours-holidays');
    } elseif ((int) $file['size'] > OHHOL_CSV_MAX_BYTES) {
        $error = __('The file is too large (maximum 1 MB).', 'opening-hours-holidays');
    } elseif (!in_array(strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)), ['csv', 'txt'], true)) {
        $error = __('Please upload a .csv file.', 'opening-hours-holidays');
    }

    if ($error === '') {
        $content = (string) file_get_contents($file['tmp_name']); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local uploaded temp file.
        $parsed  = ohhol_parse_holiday_csv($content);
        if (!$parsed['rows'] && !$parsed['skipped']) {
            $error = __('The file does not contain any entries.', 'opening-hours-holidays');
        } else {
            set_transient(ohhol_csv_transient('preview'), $parsed, 30 * MINUTE_IN_SECONDS);
        }
    }

    if ($error !== '') {
        set_transient(ohhol_csv_transient('notice'), ['error', $error], MINUTE_IN_SECONDS);
    }
    wp_safe_redirect(ohhol_settings_url());
    exit;
}
add_action('admin_post_ohhol_csv_preview', 'ohhol_handle_csv_preview');

/**
 * Confirm: merge the previewed entries into the saved holidays.
 */
function ohhol_handle_csv_import(): void {
    ohhol_csv_check_capability();
    check_admin_referer('ohhol_csv_import');
    $key    = ohhol_csv_transient('preview');
    $parsed = get_transient($key);
    delete_transient($key);

    if (isset($_POST['cancel']) || !is_array($parsed) || empty($parsed['rows'])) {
        wp_safe_redirect(ohhol_settings_url());
        exit;
    }

    $merged = [];
    if (empty($_POST['replace'])) {
        foreach (ohhol_get_holidays() as $entry) {
            $merged[$entry['date'] . '|' . (int) $entry['recurring']] = $entry;
        }
    }
    foreach ($parsed['rows'] as $entry) {
        // Same start date: the imported entry replaces the existing one.
        $merged[$entry['date'] . '|' . (int) $entry['recurring']] = $entry;
    }
    update_option('ohhol_holidays', array_values($merged));

    $count = count($parsed['rows']);
    set_transient(ohhol_csv_transient('notice'), ['success', sprintf(
        /* translators: %d: number of imported entries */
        _n('%d entry imported.', '%d entries imported.', $count, 'opening-hours-holidays'),
        $count
    )], MINUTE_IN_SECONDS);

    wp_safe_redirect(ohhol_settings_url());
    exit;
}
add_action('admin_post_ohhol_csv_import', 'ohhol_handle_csv_import');

/**
 * Settings page section: import/export, preview and result notice.
 */
function ohhol_render_csv_section(): void {
    $notice = get_transient(ohhol_csv_transient('notice'));
    if ($notice) {
        delete_transient(ohhol_csv_transient('notice'));
    }
    $preview     = get_transient(ohhol_csv_transient('preview'));
    $date_format = get_option('date_format');
    ?>
    <div class="ohhol-csv-box" id="ohhol-csv">
        <h2><?php esc_html_e('Import & export holidays (CSV)', 'opening-hours-holidays'); ?></h2>

        <?php if (is_array($notice)): ?>
            <div class="notice notice-<?php echo $notice[0] === 'success' ? 'success' : 'error'; ?> inline"><p><?php echo esc_html($notice[1]); ?></p></div>
        <?php endif; ?>

        <?php if (is_array($preview)): ?>
            <h3><?php esc_html_e('Preview', 'opening-hours-holidays'); ?></h3>
            <p>
                <?php
                printf(
                    /* translators: 1: number of valid rows, 2: number of skipped rows */
                    esc_html__('%1$d valid entries, %2$d skipped rows. Nothing has been saved yet.', 'opening-hours-holidays'),
                    count($preview['rows']),
                    count($preview['skipped'])
                );
                ?>
            </p>
            <table class="widefat striped ohhol-csv-preview">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'opening-hours-holidays'); ?></th>
                        <th><?php echo esc_html_x('Title', 'CSV column', 'opening-hours-holidays'); ?></th>
                        <th><?php esc_html_e('Status', 'opening-hours-holidays'); ?></th>
                        <th><?php esc_html_e('Every year', 'opening-hours-holidays'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preview['rows'] as $row): ?>
                        <tr>
                            <td>
                                <?php
                                echo esc_html(ohhol_format_date($row['date'], $date_format));
                                if ($row['date_end'] !== '') {
                                    echo ' – ' . esc_html(ohhol_format_date($row['date_end'], $date_format));
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html($row['note']); ?></td>
                            <td>
                                <?php
                                echo $row['status'] === 'modified'
                                    ? esc_html(ohhol_format_hours($row['open1'], $row['close1'], $row['open2'], $row['close2']))
                                    : esc_html__('Closed', 'opening-hours-holidays');
                                ?>
                            </td>
                            <td><?php echo $row['recurring'] ? esc_html__('Yes', 'opening-hours-holidays') : '–'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach ($preview['skipped'] as $skip): ?>
                        <tr class="ohhol-csv-skipped">
                            <td colspan="4">
                                <?php
                                printf(
                                    /* translators: 1: line number, 2: reason, 3: line content */
                                    esc_html__('Line %1$d skipped: %2$s – "%3$s"', 'opening-hours-holidays'),
                                    (int) $skip[0],
                                    esc_html($skip[2]),
                                    esc_html(wp_html_excerpt((string) $skip[1], 80, '…'))
                                );
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="ohhol_csv_import">
                <?php wp_nonce_field('ohhol_csv_import'); ?>
                <p>
                    <label>
                        <input type="checkbox" name="replace" value="1">
                        <?php esc_html_e('Replace all existing entries (otherwise entries are added and entries with the same date are overwritten)', 'opening-hours-holidays'); ?>
                    </label>
                </p>
                <p>
                    <?php if ($preview['rows']): ?>
                        <button type="submit" class="button button-primary"><?php esc_html_e('Import entries', 'opening-hours-holidays'); ?></button>
                    <?php endif; ?>
                    <button type="submit" name="cancel" value="1" class="button"><?php esc_html_e('Cancel', 'opening-hours-holidays'); ?></button>
                </p>
            </form>
        <?php else: ?>
            <p class="description">
                <?php esc_html_e('Import many dates at once, e.g. public holidays from a spreadsheet. Column A: date (25.12.2026 or 2026-12-25), column B: title. Every row is imported as "Closed". The export contains all fields and can be imported again.', 'opening-hours-holidays'); ?>
            </p>
            <p>
                <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ohhol_csv_template'), 'ohhol_csv_template')); ?>"><?php esc_html_e('Download template', 'opening-hours-holidays'); ?></a>
                <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ohhol_csv_export'), 'ohhol_csv_export')); ?>"><?php esc_html_e('Export entries', 'opening-hours-holidays'); ?></a>
            </p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="ohhol_csv_preview">
                <?php wp_nonce_field('ohhol_csv_preview'); ?>
                <p>
                    <label for="ohhol-csv-file" class="screen-reader-text"><?php esc_html_e('CSV file', 'opening-hours-holidays'); ?></label>
                    <input type="file" id="ohhol-csv-file" name="ohhol_csv_file" accept=".csv,text/csv,.txt">
                    <button type="submit" class="button"><?php esc_html_e('Upload and preview', 'opening-hours-holidays'); ?></button>
                </p>
            </form>
        <?php endif; ?>
    </div>
    <?php
}
