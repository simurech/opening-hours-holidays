<?php
/**
 * Schema.org structured data (JSON-LD) for local SEO.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Print LocalBusiness JSON-LD with opening hours in the site head.
 */
function ohhol_output_schema() {
    $options = ohhol_get_options();
    if (empty($options['schema_enabled'])) {
        return;
    }

    $day_map = [
        'monday'    => 'Monday',
        'tuesday'   => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday'  => 'Thursday',
        'friday'    => 'Friday',
        'saturday'  => 'Saturday',
        'sunday'    => 'Sunday',
    ];

    // Regular hours: one spec per identical time-window set, days grouped.
    $windows_by_day = [];
    foreach (ohhol_get_hours() as $key => $day) {
        if (empty($day['open'])) {
            continue;
        }
        $windows = [];
        if ($day['open1'] && $day['close1']) {
            $windows[] = [$day['open1'], $day['close1']];
        }
        if ($day['open2'] && $day['close2']) {
            $windows[] = [$day['open2'], $day['close2']];
        }
        foreach ($windows as $window) {
            $windows_by_day[$window[0] . '|' . $window[1]][] = $day_map[$key];
        }
    }

    $specs = [];
    foreach ($windows_by_day as $window => $days) {
        list($opens, $closes) = explode('|', $window);
        $specs[] = [
            '@type'     => 'OpeningHoursSpecification',
            'dayOfWeek' => $days,
            'opens'     => $opens,
            'closes'    => $closes,
        ];
    }

    // Holiday exceptions for the next 90 days.
    $today    = current_time('Y-m-d');
    $horizon  = gmdate('Y-m-d', strtotime($today . ' +90 days'));
    $specials = [];
    foreach (ohhol_get_holidays() as $entry) {
        if ($entry['date'] < $today || $entry['date'] > $horizon) {
            continue;
        }
        if ($entry['status'] === 'modified' && $entry['open1'] && $entry['close1']) {
            $specials[] = [
                '@type'        => 'OpeningHoursSpecification',
                'validFrom'    => $entry['date'],
                'validThrough' => $entry['date'],
                'opens'        => $entry['open1'],
                'closes'       => $entry['close1'],
            ];
            if ($entry['open2'] && $entry['close2']) {
                $specials[] = [
                    '@type'        => 'OpeningHoursSpecification',
                    'validFrom'    => $entry['date'],
                    'validThrough' => $entry['date'],
                    'opens'        => $entry['open2'],
                    'closes'       => $entry['close2'],
                ];
            }
        } else {
            // "00:00 – 00:00" is the documented way to mark a day as closed.
            $specials[] = [
                '@type'        => 'OpeningHoursSpecification',
                'validFrom'    => $entry['date'],
                'validThrough' => $entry['date'],
                'opens'        => '00:00',
                'closes'       => '00:00',
            ];
        }
    }

    $schema = [
        '@context'                  => 'https://schema.org',
        '@type'                     => 'LocalBusiness',
        'name'                      => $options['schema_name'] !== '' ? $options['schema_name'] : get_bloginfo('name'),
        'url'                       => home_url('/'),
        'openingHoursSpecification' => $specs,
    ];
    if ($specials) {
        $schema['specialOpeningHoursSpecification'] = $specials;
    }

    /**
     * Filter the LocalBusiness schema array before output,
     * e.g. to add address, telephone or a more specific @type.
     *
     * @param array $schema The schema.org data.
     */
    $schema = apply_filters('ohhol_schema_data', $schema);

    // Slashes stay escaped and <, >, & are hex-encoded, so no value can close the script tag.
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}
add_action('wp_head', 'ohhol_output_schema');
