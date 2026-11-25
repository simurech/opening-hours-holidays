<?php
/**
 * Schema.org structured data (JSON-LD) for local SEO.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Selectable schema.org business types (type => label).
 */
function ohhol_get_schema_types(): array {
    return [
        'LocalBusiness'            => __('Local business (generic)', 'opening-hours-holidays'),
        'Store'                    => __('Store / shop', 'opening-hours-holidays'),
        'Restaurant'               => __('Restaurant', 'opening-hours-holidays'),
        'CafeOrCoffeeShop'         => __('Café', 'opening-hours-holidays'),
        'Bakery'                   => __('Bakery', 'opening-hours-holidays'),
        'BarOrPub'                 => __('Bar / pub', 'opening-hours-holidays'),
        'FoodEstablishment'        => __('Food establishment (other)', 'opening-hours-holidays'),
        'HairSalon'                => __('Hair salon', 'opening-hours-holidays'),
        'BeautySalon'              => __('Beauty salon', 'opening-hours-holidays'),
        'HealthAndBeautyBusiness'  => __('Health & beauty business', 'opening-hours-holidays'),
        'MedicalBusiness'          => __('Medical practice', 'opening-hours-holidays'),
        'Dentist'                  => __('Dentist', 'opening-hours-holidays'),
        'Pharmacy'                 => __('Pharmacy', 'opening-hours-holidays'),
        'AutoRepair'               => __('Car repair / garage', 'opening-hours-holidays'),
        'HomeAndConstructionBusiness' => __('Trades & construction', 'opening-hours-holidays'),
        'ProfessionalService'      => __('Professional service', 'opening-hours-holidays'),
        'LegalService'             => __('Legal service', 'opening-hours-holidays'),
        'FinancialService'         => __('Financial service', 'opening-hours-holidays'),
        'RealEstateAgent'          => __('Real estate agent', 'opening-hours-holidays'),
        'SportsActivityLocation'   => __('Sports / fitness', 'opening-hours-holidays'),
        'LodgingBusiness'          => __('Hotel / lodging', 'opening-hours-holidays'),
        'Library'                  => __('Library', 'opening-hours-holidays'),
        'GovernmentOffice'         => __('Government office', 'opening-hours-holidays'),
    ];
}

/**
 * Active plugins that output their own LocalBusiness data (name => true).
 */
function ohhol_get_schema_conflicts(): array {
    $conflicts = [];
    if (defined('WPSEO_LOCAL_VERSION') || class_exists('WPSEO_Local_Core')) {
        $conflicts[] = 'Yoast Local SEO';
    }
    if (class_exists('RankMath') && method_exists('RankMath\\Helper', 'is_module_active') && \RankMath\Helper::is_module_active('local-seo')) {
        $conflicts[] = 'Rank Math (Local SEO)';
    }
    /**
     * Filter the list of detected plugins that output LocalBusiness structured data.
     *
     * @param string[] $conflicts Plugin names.
     */
    return (array) apply_filters('ohhol_schema_conflicts', $conflicts);
}

/**
 * Admin hint when another plugin already outputs LocalBusiness data.
 */
function ohhol_render_schema_conflict_notice(): void {
    $conflicts = ohhol_get_schema_conflicts();
    if (!$conflicts) {
        return;
    }
    ?>
    <div class="notice notice-warning inline ohhol-schema-conflict">
        <p>
            <?php
            printf(
                /* translators: %s: plugin name(s), e.g. "Yoast Local SEO" */
                esc_html__('%s is active and may already output LocalBusiness structured data. To avoid duplicate data for search engines, enable only one of them – or keep ours disabled and add your opening hours there.', 'opening-hours-holidays'),
                esc_html(implode(', ', $conflicts))
            );
            ?>
        </p>
    </div>
    <?php
}

/**
 * Build the LocalBusiness schema array.
 */
function ohhol_get_schema_data(): array {
    $options = ohhol_get_options();

    // Regular hours: one spec per identical time window, days grouped.
    $windows_by_day = [];
    foreach (ohhol_get_hours() as $key => $day) {
        if (empty($day['open'])) {
            continue;
        }
        foreach (ohhol_get_windows($day) as $window) {
            $windows_by_day[$window[0] . '|' . $window[1]][] = ucfirst($key);
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
    $today    = ohhol_today();
    $specials = [];
    foreach (ohhol_get_holiday_occurrences($today, ohhol_add_days($today, 90)) as $occ) {
        $entry   = $occ['entry'];
        $windows = $entry['status'] === 'modified' ? ohhol_get_windows($entry) : [];
        if (!$windows) {
            // "00:00 – 00:00" is the documented way to mark a day as closed.
            $windows = [['00:00', '00:00']];
        }
        foreach ($windows as $window) {
            $specials[] = [
                '@type'        => 'OpeningHoursSpecification',
                'validFrom'    => $occ['start'],
                'validThrough' => $occ['end'],
                'opens'        => $window[0],
                'closes'       => $window[1],
            ];
        }
    }

    $schema = [
        '@context'                  => 'https://schema.org',
        '@type'                     => $options['schema_type'] ?: 'LocalBusiness',
        '@id'                       => home_url('/#localbusiness'),
        'name'                      => $options['schema_name'] !== '' ? $options['schema_name'] : get_bloginfo('name'),
        'url'                       => home_url('/'),
        'openingHoursSpecification' => $specs,
    ];

    $address = array_filter([
        'streetAddress'   => $options['schema_street'],
        'postalCode'      => $options['schema_postal_code'],
        'addressLocality' => $options['schema_city'],
        'addressRegion'   => $options['schema_region'],
        'addressCountry'  => $options['schema_country'],
    ], 'strlen');
    if ($address) {
        $schema['address'] = ['@type' => 'PostalAddress'] + $address;
    }
    if ($options['schema_telephone'] !== '') {
        $schema['telephone'] = $options['schema_telephone'];
    }
    if ($specials) {
        $schema['specialOpeningHoursSpecification'] = $specials;
    }

    /**
     * Filter the LocalBusiness schema array before output,
     * e.g. to add geo coordinates, a logo or price range.
     *
     * @param array $schema The schema.org data.
     */
    return (array) apply_filters('ohhol_schema_data', $schema);
}

/**
 * Print LocalBusiness JSON-LD with opening hours in the site head.
 */
function ohhol_output_schema(): void {
    $options = ohhol_get_options();
    $enabled = !empty($options['schema_enabled'])
        && ($options['schema_scope'] !== 'front' || is_front_page());

    /**
     * Filter whether the JSON-LD block is printed on the current page.
     *
     * @param bool $enabled Whether to output the schema.
     */
    if (!apply_filters('ohhol_output_schema', $enabled)) {
        return;
    }

    // Slashes stay escaped and <, >, & are hex-encoded, so no value can close the script tag.
    echo '<script type="application/ld+json">' . wp_json_encode(ohhol_get_schema_data(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}
add_action('wp_head', 'ohhol_output_schema');
