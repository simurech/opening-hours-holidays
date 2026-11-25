<?php
/**
 * Shortcodes.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode tags handled by this plugin (incl. legacy aliases).
 */
function ohhol_get_shortcode_tags(): array {
    return [
        'opening_hours'           => 'ohhol_shortcode_week',
        'opening_hours_today'     => 'ohhol_shortcode_today',
        'opening_hours_holidays'  => 'ohhol_shortcode_holidays',
        'opening_hours_compact'   => 'ohhol_shortcode_compact',
        // Legacy aliases so existing content keeps working.
        'oeffnungszeiten'         => 'ohhol_shortcode_week',
        'oeffnungszeiten_heute'   => 'ohhol_shortcode_today',
        'oeffnungszeiten_spezial' => 'ohhol_shortcode_holidays',
        'oeffnungszeiten_kompakt' => 'ohhol_shortcode_compact',
    ];
}

/**
 * Register shortcodes.
 */
function ohhol_register_shortcodes(): void {
    foreach (ohhol_get_shortcode_tags() as $tag => $callback) {
        add_shortcode($tag, $callback);
    }
}
add_action('init', 'ohhol_register_shortcodes');

/**
 * Format a stored "HH:MM" time using the site's time format (Settings → General).
 */
function ohhol_format_time(string $time): string {
    if ($time === '') {
        return '';
    }
    $format = (string) get_option('time_format');
    if ($format === '') {
        $format = 'H:i';
    }
    /**
     * Filter the PHP date format used to display opening times.
     *
     * @param string $format Date format, defaults to the site's time format.
     */
    $format    = apply_filters('ohhol_time_format', $format);
    $timestamp = strtotime('1970-01-01 ' . $time . ':00 UTC');
    if ($timestamp === false) {
        return $time;
    }
    return wp_date($format, $timestamp, new DateTimeZone('UTC'));
}

/**
 * Format time windows as plain text, e.g. "09:00 – 12:00 / 13:30 – 18:00".
 * Returns '' when no valid window exists (caller renders "Closed").
 */
function ohhol_format_hours($open1, $close1, $open2 = '', $close2 = ''): string {
    if (empty($open1) || empty($close1)) {
        return '';
    }
    $output = ohhol_format_time($open1) . ' – ' . ohhol_format_time($close1);
    if (!empty($open2) && !empty($close2)) {
        $output .= ' / ' . ohhol_format_time($open2) . ' – ' . ohhol_format_time($close2);
    }
    return $output;
}

/**
 * Map an English PHP weekday name ("Monday") to the internal key.
 */
function ohhol_get_weekday_key($php_day): string {
    $key = strtolower((string) $php_day);
    return array_key_exists($key, ohhol_get_weekdays()) ? $key : 'monday';
}

/**
 * Get the holiday entry for a specific date (Y-m-d) or null.
 */
function ohhol_get_holiday_for_date(string $date): ?array {
    foreach (ohhol_get_holidays() as $entry) {
        if ($entry['date'] === $date) {
            return $entry;
        }
    }
    return null;
}

/**
 * Validate a title tag attribute against a whitelist.
 */
function ohhol_sanitize_title_tag($tag, string $default = 'h2'): string {
    $allowed = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
    return in_array($tag, $allowed, true) ? $tag : $default;
}

/**
 * Register frontend assets (enqueued on demand).
 */
function ohhol_register_assets(): void {
    wp_register_style('ohhol-frontend', OHHOL_URL . 'assets/css/frontend.css', [], OHHOL_VERSION);
    wp_register_script('ohhol-frontend', OHHOL_URL . 'assets/js/frontend.js', [], OHHOL_VERSION, [
        'in_footer' => true,
        'strategy'  => 'defer',
    ]);
}
add_action('wp_enqueue_scripts', 'ohhol_register_assets', 5);

/**
 * Enqueue assets early (in <head>) when the current post uses a shortcode,
 * so the stylesheet is not printed late in the footer.
 */
function ohhol_maybe_enqueue_early(): void {
    if (!is_singular()) {
        return;
    }
    $post = get_post();
    if (!$post || $post->post_content === '') {
        return;
    }
    foreach (array_keys(ohhol_get_shortcode_tags()) as $tag) {
        if (has_shortcode($post->post_content, $tag)) {
            ohhol_enqueue_assets();
            return;
        }
    }
}
add_action('wp_enqueue_scripts', 'ohhol_maybe_enqueue_early', 20);

/**
 * Data passed to the frontend script.
 */
function ohhol_get_frontend_data(): array {
    $tz_now    = new DateTime('now', wp_timezone());
    $yesterday = wp_date('Y-m-d', time() - DAY_IN_SECONDS);

    $regular = [];
    foreach (ohhol_get_hours() as $key => $day) {
        $day['text']   = $day['open'] ? ohhol_format_hours($day['open1'], $day['close1'], $day['open2'], $day['close2']) : '';
        $regular[$key] = $day;
    }

    $holidays = [];
    foreach (ohhol_get_holidays() as $entry) {
        // Yesterday is included for overnight windows that end today.
        if ($entry['date'] < $yesterday) {
            continue;
        }
        $entry['text'] = $entry['status'] === 'modified'
            ? ohhol_format_hours($entry['open1'], $entry['close1'], $entry['open2'], $entry['close2'])
            : '';
        $holidays[] = $entry;
    }

    return [
        'regular'    => $regular,
        'holidays'   => $holidays,
        'weekdays'   => ohhol_get_weekdays(),
        'timezone'   => wp_timezone_string(),
        'utc_offset' => (int) ($tz_now->getOffset() / 60),
        'i18n'       => [
            'closed'    => __('Closed', 'opening-hours-holidays'),
            'openNow'   => __('Open now', 'opening-hours-holidays'),
            'closedNow' => __('Currently closed', 'opening-hours-holidays'),
            'today'     => __('Today', 'opening-hours-holidays'),
            /* translators: prefix shown before the regular hours when a holiday exception applies */
            'regular'   => __('Regular:', 'opening-hours-holidays'),
        ],
    ];
}

/**
 * Enqueue frontend assets once per request (only when a shortcode is rendered).
 */
function ohhol_enqueue_assets(): void {
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    if (!wp_style_is('ohhol-frontend', 'registered')) {
        ohhol_register_assets();
    }

    wp_enqueue_style('ohhol-frontend');
    wp_add_inline_style('ohhol-frontend', ohhol_generate_inline_styles(ohhol_get_design()));

    wp_enqueue_script('ohhol-frontend');
    wp_add_inline_script(
        'ohhol-frontend',
        'var ohholData = ' . wp_json_encode(ohhol_get_frontend_data(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ';',
        'before'
    );
}

/**
 * Build inline CSS overriding the custom properties with the design settings.
 * All values are validated hex colors (see ohhol_sanitize_design()).
 */
function ohhol_generate_inline_styles(array $design): string {
    $defaults = ohhol_get_design_defaults();
    $map      = [
        '--ohhol-today-bg'   => 'today_bg',
        '--ohhol-today-text' => 'today_text',
        '--ohhol-text-muted' => 'text_muted',
        '--ohhol-text-faint' => 'text_faint',
        '--ohhol-closed'     => 'closed_color',
        '--ohhol-open-bg'    => 'open_bg',
        '--ohhol-open-text'  => 'open_text',
        '--ohhol-open-dot'   => 'open_dot',
        '--ohhol-shut-bg'    => 'closed_bg',
        '--ohhol-shut-text'  => 'closed_text',
        '--ohhol-shut-dot'   => 'closed_dot',
    ];

    $css = ':root {';
    foreach ($map as $prop => $field) {
        $css .= ' ' . $prop . ': ' . (sanitize_hex_color($design[$field] ?? '') ?: $defaults[$field]) . ';';
    }
    $css .= ' }';

    if (!empty($design['zebra_enabled'])) {
        $css .= sprintf(
            ' .ohhol-week-table tr:nth-child(odd), .ohhol-holidays-list tr:nth-child(odd) { background-color: %s; }',
            sanitize_hex_color($design['zebra_color'] ?? '') ?: $defaults['zebra_color']
        );
    }

    $css .= ' .ohhol-status:empty { display: none; }';

    return $css;
}

/**
 * Render an optional title with a whitelisted heading tag.
 */
function ohhol_render_title(string $title, string $tag): string {
    if ($title === '') {
        return '';
    }
    return sprintf('<%1$s class="ohhol-title">%2$s</%1$s>', tag_escape($tag), esc_html($title));
}

/**
 * Shortcode: weekly overview [opening_hours]
 *
 * Renders the regular hours (cache-safe). The "today" marker and any
 * holiday overlay for the next 7 days are applied client-side.
 */
function ohhol_shortcode_week($atts): string {
    $atts = shortcode_atts([
        'title'     => '',
        'title_tag' => 'h2',
    ], $atts, 'opening_hours');

    ohhol_enqueue_assets();

    $weekdays = ohhol_get_weekdays_ordered();
    $hours    = ohhol_get_hours();

    ob_start();
    ?>
    <div class="ohhol-week">
        <?php echo ohhol_render_title((string) $atts['title'], ohhol_sanitize_title_tag($atts['title_tag'])); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in ohhol_render_title() ?>
        <table class="ohhol-week-table">
            <caption class="screen-reader-text"><?php esc_html_e('Weekly opening hours', 'opening-hours-holidays'); ?></caption>
            <tbody>
                <?php foreach ($weekdays as $key => $label):
                    $day       = $hours[$key];
                    $formatted = $day['open'] ? ohhol_format_hours($day['open1'], $day['close1'], $day['open2'], $day['close2']) : '';
                ?>
                <tr data-day="<?php echo esc_attr($key); ?>">
                    <th scope="row" class="ohhol-day-name"><?php echo esc_html($label); ?></th>
                    <td class="ohhol-day-hours">
                        <?php if ($formatted !== ''): ?>
                            <?php echo esc_html($formatted); ?>
                        <?php else: ?>
                            <span class="ohhol-closed"><?php esc_html_e('Closed', 'opening-hours-holidays'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * Shortcode: today [opening_hours_today]
 *
 * The visible content is built entirely client-side so full-page caching
 * never shows a stale day. A server-rendered fallback lives in <noscript>.
 */
function ohhol_shortcode_today($atts): string {
    ohhol_enqueue_assets();

    $weekdays   = ohhol_get_weekdays();
    $hours      = ohhol_get_hours();
    $options    = ohhol_get_options();
    $today      = current_time('Y-m-d');
    $day_key    = ohhol_get_weekday_key(current_time('l'));
    $day_label  = $weekdays[$day_key];
    $holiday    = ohhol_get_holiday_for_date($today);
    $note       = '';
    $hours_text = '';

    if ($holiday) {
        if ($holiday['status'] !== 'closed') {
            $hours_text = ohhol_format_hours($holiday['open1'], $holiday['close1'], $holiday['open2'], $holiday['close2']);
        }
        $note = $holiday['note'];
    } else {
        $day = $hours[$day_key];
        if ($day['open']) {
            $hours_text = ohhol_format_hours($day['open1'], $day['close1'], $day['open2'], $day['close2']);
        }
    }
    $is_closed = ($hours_text === '');

    ob_start();
    ?>
    <div class="ohhol-today" data-ohhol-today>
        <?php if ($options['show_status']): ?>
            <span class="ohhol-status" data-ohhol-status role="status"></span>
        <?php endif; ?>
    </div>
    <noscript>
        <div class="ohhol-today">
            <span class="ohhol-today-day"><?php echo esc_html($day_label); ?>:</span>
            <span class="ohhol-today-hours <?php echo $is_closed ? 'ohhol-closed' : ''; ?>">
                <?php echo $is_closed ? esc_html__('Closed', 'opening-hours-holidays') : esc_html($hours_text); ?>
            </span>
            <?php if ($note): ?>
                <span class="ohhol-today-note">(<?php echo esc_html($note); ?>)</span>
            <?php endif; ?>
        </div>
    </noscript>
    <?php
    return (string) ob_get_clean();
}

/**
 * Format a Y-m-d date for display in the site's time zone.
 */
function ohhol_format_date(string $date, string $format): string {
    try {
        $dt = new DateTimeImmutable($date . ' 12:00:00', wp_timezone());
    } catch (Exception $e) {
        return $date;
    }
    return wp_date($format, $dt->getTimestamp());
}

/**
 * Shortcode: upcoming holidays [opening_hours_holidays]
 *
 * PHP only filters out clearly past entries; the days window and limit are
 * applied client-side based on the visitor's real date (cache-safe).
 */
function ohhol_shortcode_holidays($atts): string {
    $atts = shortcode_atts([
        'limit'     => -1,
        'days'      => 30,
        'title'     => '',
        'title_tag' => 'h2',
        // Legacy attribute name from the pre-2.0 plugin.
        'tage'      => null,
    ], $atts, 'opening_hours_holidays');

    if ($atts['tage'] !== null) {
        $atts['days'] = $atts['tage'];
    }

    $today    = current_time('Y-m-d');
    $upcoming = array_filter(ohhol_get_holidays(), static function ($e) use ($today) {
        return $e['date'] >= $today;
    });

    if (empty($upcoming)) {
        return '';
    }

    ohhol_enqueue_assets();

    /* translators: date format for holiday entries, see https://www.php.net/manual/datetime.format.php */
    $date_format = _x('l, F j, Y', 'holiday date format', 'opening-hours-holidays');

    ob_start();
    ?>
    <div class="ohhol-holidays"
         data-ohhol-holidays
         data-days="<?php echo (int) $atts['days']; ?>"
         data-limit="<?php echo (int) $atts['limit']; ?>">
        <?php echo ohhol_render_title((string) $atts['title'], ohhol_sanitize_title_tag($atts['title_tag'])); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in ohhol_render_title() ?>
        <table class="ohhol-holidays-list">
            <caption class="screen-reader-text"><?php esc_html_e('Upcoming holidays and exceptions', 'opening-hours-holidays'); ?></caption>
            <tbody>
                <?php foreach ($upcoming as $entry):
                    $formatted = $entry['status'] === 'modified'
                        ? ohhol_format_hours($entry['open1'], $entry['close1'], $entry['open2'], $entry['close2'])
                        : '';
                ?>
                <tr data-date="<?php echo esc_attr($entry['date']); ?>">
                    <td class="ohhol-holiday-date"><?php echo esc_html(ohhol_format_date($entry['date'], $date_format)); ?></td>
                    <td class="ohhol-holiday-info">
                        <?php if ($formatted !== ''): ?>
                            <?php echo esc_html($formatted); ?>
                        <?php else: ?>
                            <span class="ohhol-closed"><?php esc_html_e('Closed', 'opening-hours-holidays'); ?></span>
                        <?php endif; ?>
                        <?php if ($entry['note']): ?>
                            <span class="ohhol-holiday-note">(<?php echo esc_html($entry['note']); ?>)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * Group consecutive days with identical hours,
 * e.g. [['label' => 'Monday – Friday', 'day' => [...]], ...].
 */
function ohhol_group_days_by_hours(array $hours, array $weekdays): array {
    $keys   = array_keys($weekdays);
    $count  = count($keys);
    $groups = [];
    $i      = 0;

    while ($i < $count) {
        $current = $hours[$keys[$i]];
        $start   = $i;

        while ($i + 1 < $count && $hours[$keys[$i + 1]] == $current) {
            $i++;
        }

        $label = $start === $i
            ? $weekdays[$keys[$start]]
            : $weekdays[$keys[$start]] . ' – ' . $weekdays[$keys[$i]];

        $groups[] = ['label' => $label, 'day' => $current];
        $i++;
    }

    return $groups;
}

/**
 * Shortcode: compact overview [opening_hours_compact]
 *
 * For footers/sidebars. Groups days with identical hours. The status badge
 * and today's holiday note are rendered client-side (cache-safe).
 */
function ohhol_shortcode_compact($atts): string {
    $atts = shortcode_atts([
        'status'    => 0,
        'title'     => '',
        'title_tag' => 'h3',
    ], $atts, 'opening_hours_compact');

    ohhol_enqueue_assets();

    $hours       = ohhol_get_hours();
    $options     = ohhol_get_options();
    $groups      = ohhol_group_days_by_hours($hours, ohhol_get_weekdays_ordered());
    $show_status = (int) $atts['status'] || $options['show_status'];

    ob_start();
    ?>
    <div class="ohhol-compact">
        <?php echo ohhol_render_title((string) $atts['title'], ohhol_sanitize_title_tag($atts['title_tag'], 'h3')); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in ohhol_render_title() ?>

        <?php if ($show_status): ?>
            <div class="ohhol-compact-status">
                <span class="ohhol-status" data-ohhol-status role="status"></span>
                <span class="ohhol-today-note" data-ohhol-today-note hidden></span>
            </div>
        <?php endif; ?>

        <ul class="ohhol-compact-list">
            <?php foreach ($groups as $group):
                $day       = $group['day'];
                $formatted = $day['open'] ? ohhol_format_hours($day['open1'], $day['close1'], $day['open2'], $day['close2']) : '';
            ?>
            <li class="ohhol-compact-row">
                <span class="ohhol-compact-day"><?php echo esc_html($group['label']); ?></span>
                <span class="ohhol-compact-hours">
                    <?php if ($formatted !== ''): ?>
                        <?php echo esc_html($formatted); ?>
                    <?php else: ?>
                        <span class="ohhol-closed"><?php esc_html_e('Closed', 'opening-hours-holidays'); ?></span>
                    <?php endif; ?>
                </span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
    return (string) ob_get_clean();
}
