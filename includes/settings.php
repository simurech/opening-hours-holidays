<?php
/**
 * Settings: opening hours, holidays, options, design.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin menu.
 */
function ohhol_admin_menu() {
    add_menu_page(
        __('Opening Hours', 'opening-hours-holidays'),
        __('Opening Hours', 'opening-hours-holidays'),
        'manage_options',
        'ohhol-settings',
        'ohhol_settings_page',
        'dashicons-clock',
        26
    );
}
add_action('admin_menu', 'ohhol_admin_menu');

/**
 * Register settings.
 */
function ohhol_register_settings(): void {
    $settings = [
        'ohhol_hours'    => 'ohhol_sanitize_hours',
        'ohhol_holidays' => 'ohhol_sanitize_holidays',
        'ohhol_options'  => 'ohhol_sanitize_options',
        'ohhol_design'   => 'ohhol_sanitize_design',
    ];
    foreach ($settings as $option => $callback) {
        register_setting('ohhol_settings', $option, [
            'type'              => 'array',
            'default'           => [],
            'show_in_rest'      => false,
            'sanitize_callback' => $callback,
        ]);
    }
}
add_action('admin_init', 'ohhol_register_settings');

/**
 * Add a settings error once per request (sanitize callbacks can run twice
 * when an option is created for the first time).
 */
function ohhol_add_settings_notice(string $code, string $message): void {
    static $added = [];
    if (isset($added[$code])) {
        return;
    }
    $added[$code] = true;
    add_settings_error('ohhol_settings', $code, $message, 'warning');
}

/**
 * Weekdays ordered according to "Week Starts On" (Settings → General).
 */
function ohhol_get_weekdays_ordered(): array {
    $weekdays = ohhol_get_weekdays();
    $keys     = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    $start    = (int) get_option('start_of_week', 1);
    $start    = ($start >= 0 && $start <= 6) ? $start : 1;

    $ordered = [];
    for ($i = 0; $i < 7; $i++) {
        $key           = $keys[($start + $i) % 7];
        $ordered[$key] = $weekdays[$key];
    }
    return $ordered;
}

/**
 * Normalize two time windows.
 *
 * A window needs both times and must not start and end at the same time.
 * A closing time earlier than the opening time means the window ends on the
 * following day (e.g. 22:00 – 02:00). Incomplete windows are cleared and
 * reported via $had_issue; a lone second window moves up to the first slot.
 */
function ohhol_normalize_windows(array $input, bool &$had_issue = false): array {
    $windows = [];
    foreach ([1, 2] as $n) {
        $open  = ohhol_sanitize_time($input['open' . $n] ?? '');
        $close = ohhol_sanitize_time($input['close' . $n] ?? '');
        if ($open === '' && $close === '') {
            continue;
        }
        if ($open === '' || $close === '' || $open === $close) {
            $had_issue = true;
            continue;
        }
        $windows[] = [$open, $close];
    }

    return [
        'open1'  => $windows[0][0] ?? '',
        'close1' => $windows[0][1] ?? '',
        'open2'  => $windows[1][0] ?? '',
        'close2' => $windows[1][1] ?? '',
    ];
}

/**
 * Weekday keys in display order with translated labels.
 */
function ohhol_get_weekdays(): array {
    return [
        'monday'    => __('Monday', 'opening-hours-holidays'),
        'tuesday'   => __('Tuesday', 'opening-hours-holidays'),
        'wednesday' => __('Wednesday', 'opening-hours-holidays'),
        'thursday'  => __('Thursday', 'opening-hours-holidays'),
        'friday'    => __('Friday', 'opening-hours-holidays'),
        'saturday'  => __('Saturday', 'opening-hours-holidays'),
        'sunday'    => __('Sunday', 'opening-hours-holidays'),
    ];
}

/**
 * Default weekly hours.
 */
function ohhol_get_hours_defaults() {
    $defaults = [];
    foreach (array_keys(ohhol_get_weekdays()) as $key) {
        $defaults[$key] = [
            'open'   => ($key !== 'sunday') ? 1 : 0,
            'open1'  => '09:00',
            'close1' => '12:00',
            'open2'  => '13:30',
            'close2' => '18:00',
        ];
    }
    return $defaults;
}

/**
 * Weekly hours (cached per request).
 */
function ohhol_get_hours() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $saved  = get_option('ohhol_hours', []);
    $cached = wp_parse_args($saved, ohhol_get_hours_defaults());
    return $cached;
}

/**
 * Holiday entries (cached per request).
 */
function ohhol_get_holidays() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = get_option('ohhol_holidays', []);
    return $cached;
}

/**
 * General options (cached per request).
 */
function ohhol_get_options() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $defaults = [
        'show_status'    => 0,
        'schema_enabled' => 0,
        'schema_name'    => '',
    ];
    $cached = wp_parse_args(get_option('ohhol_options', []), $defaults);
    return $cached;
}

/**
 * Default design values.
 */
function ohhol_get_design_defaults() {
    return [
        'today_bg'     => '#000000',
        'today_text'   => '#ffffff',
        'zebra_color'  => '#f9f9f9',
        'zebra_enabled' => 1,
        'text_muted'   => '#666666',
        'text_faint'   => '#999999',
        'closed_color' => '#c62828',
        'open_bg'      => '#e8f5e9',
        'open_text'    => '#2e7d32',
        'open_dot'     => '#4caf50',
        'closed_bg'    => '#ffebee',
        'closed_text'  => '#c62828',
        'closed_dot'   => '#f44336',
    ];
}

/**
 * Design settings (cached per request).
 */
function ohhol_get_design() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = wp_parse_args(get_option('ohhol_design', []), ohhol_get_design_defaults());
    return $cached;
}

/**
 * Sanitize: weekly hours.
 */
function ohhol_sanitize_hours($input) {
    $input     = is_array($input) ? $input : [];
    $sanitized = [];
    $had_issue = false;
    foreach (array_keys(ohhol_get_weekdays()) as $key) {
        $day             = is_array($input[$key] ?? null) ? $input[$key] : [];
        $sanitized[$key] = ['open' => isset($day['open']) ? 1 : 0] + ohhol_normalize_windows($day, $had_issue);
    }
    if ($had_issue) {
        ohhol_add_settings_notice(
            'ohhol_hours_incomplete',
            __('Some opening hours were incomplete or started and ended at the same time and have been removed. Please check your weekly hours.', 'opening-hours-holidays')
        );
    }
    return $sanitized;
}

/**
 * Sanitize: holiday entries.
 *
 * Validates dates, deduplicates by date and drops entries
 * that are more than 90 days in the past.
 */
function ohhol_sanitize_holidays($input) {
    if (!is_array($input)) {
        return [];
    }

    $cutoff    = wp_date('Y-m-d', time() - 90 * DAY_IN_SECONDS);
    $sanitized = [];
    $had_issue = false;

    foreach ($input as $item) {
        if (!is_array($item)) {
            continue;
        }
        $date = ohhol_sanitize_date($item['date'] ?? '');
        if ($date === '' || $date < $cutoff) {
            continue;
        }
        $status = (isset($item['status']) && in_array($item['status'], ['closed', 'modified'], true))
            ? $item['status']
            : 'closed';

        $windows = $status === 'modified'
            ? ohhol_normalize_windows($item, $had_issue)
            : ['open1' => '', 'close1' => '', 'open2' => '', 'close2' => ''];

        // Last entry for a date wins.
        $sanitized[$date] = [
            'date'   => $date,
            'status' => $status,
        ] + $windows + [
            'note'   => sanitize_text_field($item['note'] ?? ''),
        ];
    }

    if ($had_issue) {
        ohhol_add_settings_notice(
            'ohhol_holidays_incomplete',
            __('Some holiday hours were incomplete and have been removed. Please check your exceptions.', 'opening-hours-holidays')
        );
    }

    ksort($sanitized);
    return array_values($sanitized);
}

/**
 * Sanitize: general options.
 */
function ohhol_sanitize_options($input) {
    $input = is_array($input) ? $input : [];
    return [
        'show_status'    => isset($input['show_status']) ? 1 : 0,
        'schema_enabled' => isset($input['schema_enabled']) ? 1 : 0,
        'schema_name'    => sanitize_text_field($input['schema_name'] ?? ''),
    ];
}

/**
 * Sanitize: design settings.
 */
function ohhol_sanitize_design($input) {
    $input     = is_array($input) ? $input : [];
    $sanitized = [];
    $defaults  = ohhol_get_design_defaults();

    foreach ($defaults as $field => $default) {
        if ($field === 'zebra_enabled') {
            continue;
        }
        if (isset($input[$field])) {
            $color = sanitize_hex_color($input[$field]);
            $sanitized[$field] = $color ?: $default;
        }
    }

    $sanitized['zebra_enabled'] = isset($input['zebra_enabled']) ? 1 : 0;
    return $sanitized;
}

/**
 * Sanitize a time string (HH:MM, 24h).
 */
function ohhol_sanitize_time($time) {
    $time = sanitize_text_field((string) $time);
    if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $time, $m)) {
        // Zero-pad so times compare correctly as strings ("09:00" < "10:00").
        return sprintf('%02d:%s', (int) $m[1], $m[2]);
    }
    return '';
}

/**
 * Sanitize a date string (Y-m-d). Returns '' if invalid.
 */
function ohhol_sanitize_date($date) {
    $date = sanitize_text_field($date);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
        return '';
    }
    if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return '';
    }
    return $date;
}

/**
 * Render one holiday table row (used for saved entries; the JS template
 * for new rows lives in admin/admin.js and must be kept in sync).
 */
function ohhol_render_holiday_row($index, $entry, $is_past) {
    ?>
    <tr class="<?php echo $is_past ? 'ohhol-past' : ''; ?>">
        <td>
            <input type="date"
                   name="ohhol_holidays[<?php echo (int) $index; ?>][date]"
                   value="<?php echo esc_attr($entry['date']); ?>">
        </td>
        <td>
            <select name="ohhol_holidays[<?php echo (int) $index; ?>][status]" class="ohhol-status-select">
                <option value="closed" <?php selected($entry['status'], 'closed'); ?>><?php esc_html_e('Closed', 'opening-hours-holidays'); ?></option>
                <option value="modified" <?php selected($entry['status'], 'modified'); ?>><?php esc_html_e('Modified hours', 'opening-hours-holidays'); ?></option>
            </select>
        </td>
        <td class="ohhol-holiday-times">
            <input type="time" name="ohhol_holidays[<?php echo (int) $index; ?>][open1]" value="<?php echo esc_attr($entry['open1']); ?>" class="ohhol-time-input">
            <span>–</span>
            <input type="time" name="ohhol_holidays[<?php echo (int) $index; ?>][close1]" value="<?php echo esc_attr($entry['close1']); ?>" class="ohhol-time-input">
            <span class="ohhol-window-sep">/</span>
            <input type="time" name="ohhol_holidays[<?php echo (int) $index; ?>][open2]" value="<?php echo esc_attr($entry['open2']); ?>" class="ohhol-time-input">
            <span>–</span>
            <input type="time" name="ohhol_holidays[<?php echo (int) $index; ?>][close2]" value="<?php echo esc_attr($entry['close2']); ?>" class="ohhol-time-input">
        </td>
        <td>
            <input type="text"
                   name="ohhol_holidays[<?php echo (int) $index; ?>][note]"
                   value="<?php echo esc_attr($entry['note']); ?>"
                   placeholder="<?php echo esc_attr__('e.g. Public holiday', 'opening-hours-holidays'); ?>"
                   class="regular-text">
        </td>
        <td>
            <button type="button" class="button ohhol-remove-row" aria-label="<?php echo esc_attr__('Remove entry', 'opening-hours-holidays'); ?>">×</button>
        </td>
    </tr>
    <?php
}

/**
 * Settings page.
 */
function ohhol_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $weekdays = ohhol_get_weekdays_ordered();
    $hours    = ohhol_get_hours();
    $holidays = ohhol_get_holidays();
    $options  = ohhol_get_options();
    $design   = ohhol_get_design();
    $today    = current_time('Y-m-d');
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Opening Hours', 'opening-hours-holidays'); ?></h1>
        <?php settings_errors(); ?>

        <form method="post" action="options.php">
            <?php settings_fields('ohhol_settings'); ?>

            <h2><?php esc_html_e('Regular opening hours', 'opening-hours-holidays'); ?></h2>
            <p class="description"><?php esc_html_e('Times are shown in the time format and week order configured under Settings → General. A closing time earlier than the opening time ends on the following day (e.g. 22:00 – 02:00).', 'opening-hours-holidays'); ?></p>
            <table class="widefat ohhol-hours-table">
                <thead>
                    <tr>
                        <th class="ohhol-col-day"><?php esc_html_e('Day', 'opening-hours-holidays'); ?></th>
                        <th class="ohhol-col-open"><?php esc_html_e('Open', 'opening-hours-holidays'); ?></th>
                        <th><?php esc_html_e('Time window 1 (e.g. morning)', 'opening-hours-holidays'); ?></th>
                        <th><?php esc_html_e('Time window 2 (e.g. afternoon)', 'opening-hours-holidays'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($weekdays as $key => $label):
                        $day = $hours[$key];
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($label); ?></strong></td>
                        <td>
                            <input type="checkbox"
                                   name="ohhol_hours[<?php echo esc_attr($key); ?>][open]"
                                   value="1"
                                   class="ohhol-open-toggle"
                                   data-day="<?php echo esc_attr($key); ?>"
                                   <?php checked($day['open'], 1); ?>>
                        </td>
                        <td class="ohhol-times-cell" data-day="<?php echo esc_attr($key); ?>">
                            <input type="time"
                                   name="ohhol_hours[<?php echo esc_attr($key); ?>][open1]"
                                   value="<?php echo esc_attr($day['open1']); ?>"
                                   class="ohhol-time-input">
                            <span>–</span>
                            <input type="time"
                                   name="ohhol_hours[<?php echo esc_attr($key); ?>][close1]"
                                   value="<?php echo esc_attr($day['close1']); ?>"
                                   class="ohhol-time-input">
                        </td>
                        <td class="ohhol-times-cell" data-day="<?php echo esc_attr($key); ?>">
                            <input type="time"
                                   name="ohhol_hours[<?php echo esc_attr($key); ?>][open2]"
                                   value="<?php echo esc_attr($day['open2']); ?>"
                                   class="ohhol-time-input">
                            <span>–</span>
                            <input type="time"
                                   name="ohhol_hours[<?php echo esc_attr($key); ?>][close2]"
                                   value="<?php echo esc_attr($day['close2']); ?>"
                                   class="ohhol-time-input">
                            <span class="description ohhol-hint"><?php esc_html_e('Leave empty if there is no break', 'opening-hours-holidays'); ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2 class="ohhol-section"><?php esc_html_e('Holidays & exceptions', 'opening-hours-holidays'); ?></h2>
            <p class="description"><?php esc_html_e('Public holidays, company holidays or deviating hours on specific dates. Entries more than 90 days in the past are removed automatically when saving.', 'opening-hours-holidays'); ?></p>

            <table class="widefat ohhol-holidays-table">
                <thead>
                    <tr>
                        <th class="ohhol-col-date"><?php esc_html_e('Date', 'opening-hours-holidays'); ?></th>
                        <th class="ohhol-col-status"><?php esc_html_e('Status', 'opening-hours-holidays'); ?></th>
                        <th><?php esc_html_e('Hours (if modified)', 'opening-hours-holidays'); ?></th>
                        <th class="ohhol-col-note"><?php esc_html_e('Note', 'opening-hours-holidays'); ?></th>
                        <th class="ohhol-col-remove"></th>
                    </tr>
                </thead>
                <tbody id="ohhol-holidays-body" data-next-index="<?php echo esc_attr(count($holidays)); ?>">
                    <?php foreach ($holidays as $index => $entry):
                        ohhol_render_holiday_row($index, $entry, $entry['date'] < $today);
                    endforeach; ?>
                </tbody>
            </table>

            <p>
                <button type="button" class="button" id="ohhol-add-holiday">+ <?php esc_html_e('Add entry', 'opening-hours-holidays'); ?></button>
            </p>

            <h2 class="ohhol-section"><?php esc_html_e('Options', 'opening-hours-holidays'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Show "Open now" status', 'opening-hours-holidays'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="ohhol_options[show_status]"
                                   value="1"
                                   <?php checked($options['show_status'], 1); ?>>
                            <?php
                            printf(
                                /* translators: %s: shortcode name */
                                esc_html__('Show whether the business is currently open in the %s shortcode', 'opening-hours-holidays'),
                                '<code>[opening_hours_today]</code>'
                            );
                            ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Schema.org structured data', 'opening-hours-holidays'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="ohhol_options[schema_enabled]"
                                   value="1"
                                   <?php checked($options['schema_enabled'], 1); ?>>
                            <?php esc_html_e('Add LocalBusiness structured data (JSON-LD) with your opening hours to every page — helps search engines display your hours', 'opening-hours-holidays'); ?>
                        </label>
                        <p style="margin-top: 10px;">
                            <label for="ohhol-schema-name"><?php esc_html_e('Business name', 'opening-hours-holidays'); ?></label><br>
                            <input type="text"
                                   id="ohhol-schema-name"
                                   name="ohhol_options[schema_name]"
                                   value="<?php echo esc_attr($options['schema_name']); ?>"
                                   class="regular-text"
                                   placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>">
                        </p>
                        <p class="description"><?php esc_html_e('Leave empty to use the site title.', 'opening-hours-holidays'); ?></p>
                    </td>
                </tr>
            </table>

            <h2 class="ohhol-section"><?php esc_html_e('Design', 'opening-hours-holidays'); ?></h2>
            <p class="description">
                <?php esc_html_e('Font and primary text color are inherited from your theme. The colors below can be customized.', 'opening-hours-holidays'); ?>
            </p>

            <h3><?php esc_html_e('Text & table', 'opening-hours-holidays'); ?></h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Muted text color', 'opening-hours-holidays'); ?></th>
                    <td>
                        <input type="color" name="ohhol_design[text_muted]" value="<?php echo esc_attr($design['text_muted']); ?>">
                        <code><?php echo esc_html($design['text_muted']); ?></code>
                        <p class="description"><?php esc_html_e('Used for times and secondary information (default: #666666)', 'opening-hours-holidays'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Faint text color', 'opening-hours-holidays'); ?></th>
                    <td>
                        <input type="color" name="ohhol_design[text_faint]" value="<?php echo esc_attr($design['text_faint']); ?>">
                        <code><?php echo esc_html($design['text_faint']); ?></code>
                        <p class="description"><?php esc_html_e('Used for notes and additions in brackets (default: #999999)', 'opening-hours-holidays'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Zebra stripes', 'opening-hours-holidays'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="ohhol_design[zebra_enabled]" value="1" <?php checked($design['zebra_enabled'], 1); ?>>
                            <?php esc_html_e('Add a background color to odd rows', 'opening-hours-holidays'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Zebra stripe color', 'opening-hours-holidays'); ?></th>
                    <td>
                        <input type="color" name="ohhol_design[zebra_color]" value="<?php echo esc_attr($design['zebra_color']); ?>">
                        <code><?php echo esc_html($design['zebra_color']); ?></code>
                    </td>
                </tr>
            </table>

            <h3 class="ohhol-subsection"><?php esc_html_e('"Today" marker', 'opening-hours-holidays'); ?></h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Background color', 'opening-hours-holidays'); ?></th>
                    <td>
                        <input type="color" name="ohhol_design[today_bg]" value="<?php echo esc_attr($design['today_bg']); ?>">
                        <code><?php echo esc_html($design['today_bg']); ?></code>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Text color', 'opening-hours-holidays'); ?></th>
                    <td>
                        <input type="color" name="ohhol_design[today_text]" value="<?php echo esc_attr($design['today_text']); ?>">
                        <code><?php echo esc_html($design['today_text']); ?></code>
                    </td>
                </tr>
            </table>

            <h3 class="ohhol-subsection"><?php esc_html_e('Status colors', 'opening-hours-holidays'); ?></h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('"Closed" text color', 'opening-hours-holidays'); ?></th>
                    <td>
                        <input type="color" name="ohhol_design[closed_color]" value="<?php echo esc_attr($design['closed_color']); ?>">
                        <code><?php echo esc_html($design['closed_color']); ?></code>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('"Open now" badge', 'opening-hours-holidays'); ?></th>
                    <td>
                        <label><?php esc_html_e('Background', 'opening-hours-holidays'); ?></label>
                        <input type="color" name="ohhol_design[open_bg]" value="<?php echo esc_attr($design['open_bg']); ?>">
                        <code><?php echo esc_html($design['open_bg']); ?></code>
                        &nbsp;&nbsp;
                        <label><?php esc_html_e('Text', 'opening-hours-holidays'); ?></label>
                        <input type="color" name="ohhol_design[open_text]" value="<?php echo esc_attr($design['open_text']); ?>">
                        <code><?php echo esc_html($design['open_text']); ?></code>
                        &nbsp;&nbsp;
                        <label><?php esc_html_e('Dot', 'opening-hours-holidays'); ?></label>
                        <input type="color" name="ohhol_design[open_dot]" value="<?php echo esc_attr($design['open_dot']); ?>">
                        <code><?php echo esc_html($design['open_dot']); ?></code>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('"Currently closed" badge', 'opening-hours-holidays'); ?></th>
                    <td>
                        <label><?php esc_html_e('Background', 'opening-hours-holidays'); ?></label>
                        <input type="color" name="ohhol_design[closed_bg]" value="<?php echo esc_attr($design['closed_bg']); ?>">
                        <code><?php echo esc_html($design['closed_bg']); ?></code>
                        &nbsp;&nbsp;
                        <label><?php esc_html_e('Text', 'opening-hours-holidays'); ?></label>
                        <input type="color" name="ohhol_design[closed_text]" value="<?php echo esc_attr($design['closed_text']); ?>">
                        <code><?php echo esc_html($design['closed_text']); ?></code>
                        &nbsp;&nbsp;
                        <label><?php esc_html_e('Dot', 'opening-hours-holidays'); ?></label>
                        <input type="color" name="ohhol_design[closed_dot]" value="<?php echo esc_attr($design['closed_dot']); ?>">
                        <code><?php echo esc_html($design['closed_dot']); ?></code>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Save settings', 'opening-hours-holidays')); ?>
        </form>

        <hr class="ohhol-section">

        <h2><?php esc_html_e('Shortcode reference', 'opening-hours-holidays'); ?></h2>
        <div class="ohhol-doc-box">
            <h3><?php esc_html_e('Weekly overview', 'opening-hours-holidays'); ?></h3>
            <code class="ohhol-doc-code">[opening_hours]</code>
            <p><?php esc_html_e('Shows a table with all weekdays and the regular opening hours. The current day is highlighted.', 'opening-hours-holidays'); ?></p>
            <ul class="ohhol-doc-list">
                <li><code>title="…"</code> – <?php esc_html_e('Optional title above the table', 'opening-hours-holidays'); ?></li>
                <li><code>title_tag="h2"</code> – <?php esc_html_e('HTML tag for the title (default: h2)', 'opening-hours-holidays'); ?></li>
            </ul>

            <h3><?php esc_html_e('Today', 'opening-hours-holidays'); ?></h3>
            <code class="ohhol-doc-code">[opening_hours_today]</code>
            <p><?php esc_html_e('Shows only the current day. Holiday exceptions are applied automatically.', 'opening-hours-holidays'); ?></p>

            <h3><?php esc_html_e('Holidays & exceptions', 'opening-hours-holidays'); ?></h3>
            <code class="ohhol-doc-code">[opening_hours_holidays]</code>
            <p><?php esc_html_e('Shows upcoming holidays and exceptions.', 'opening-hours-holidays'); ?></p>
            <ul class="ohhol-doc-list">
                <li><code>days="30"</code> – <?php esc_html_e('Only entries up to X days in the future (default: 30)', 'opening-hours-holidays'); ?></li>
                <li><code>limit="5"</code> – <?php esc_html_e('Maximum number of entries (optional)', 'opening-hours-holidays'); ?></li>
                <li><code>title="…"</code> – <?php esc_html_e('Optional title (only shown if there are entries)', 'opening-hours-holidays'); ?></li>
                <li><code>title_tag="h2"</code> – <?php esc_html_e('HTML tag for the title (default: h2)', 'opening-hours-holidays'); ?></li>
            </ul>

            <h3><?php esc_html_e('Compact overview (footer/sidebar)', 'opening-hours-holidays'); ?></h3>
            <code class="ohhol-doc-code">[opening_hours_compact]</code>
            <p><?php esc_html_e('Space-saving list. Consecutive days with identical hours are grouped automatically (e.g. "Monday – Friday").', 'opening-hours-holidays'); ?></p>
            <ul class="ohhol-doc-list">
                <li><code>status="1"</code> – <?php esc_html_e('Show the "Open now / Currently closed" badge', 'opening-hours-holidays'); ?></li>
                <li><code>title="…"</code> – <?php esc_html_e('Optional title', 'opening-hours-holidays'); ?></li>
                <li><code>title_tag="h3"</code> – <?php esc_html_e('HTML tag for the title (default: h3)', 'opening-hours-holidays'); ?></li>
            </ul>

            <p class="description">
                <?php esc_html_e('The legacy shortcodes [oeffnungszeiten], [oeffnungszeiten_heute], [oeffnungszeiten_spezial] and [oeffnungszeiten_kompakt] keep working as aliases.', 'opening-hours-holidays'); ?>
            </p>
        </div>
    </div>
    <?php
}
