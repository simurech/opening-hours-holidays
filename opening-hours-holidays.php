<?php
/**
 * Plugin Name:       Opening Hours & Holidays
 * Plugin URI:        https://pulacha-labs.ch/plugins/opening-hours/
 * Description:       Manage regular opening hours and holiday exceptions, display them via shortcodes and add schema.org structured data for local SEO.
 * Version:           2.0.4
 * Requires at least: 5.8
 * Tested up to:      7.1
 * Requires PHP:      8.3
 * Author:            Pulacha Labs
 * Author URI:        https://pulacha-labs.ch/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       opening-hours-holidays
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('OHHOL_VERSION', '2.0.4');
define('OHHOL_PATH', plugin_dir_path(__FILE__));
define('OHHOL_URL', plugin_dir_url(__FILE__));

require_once OHHOL_PATH . 'includes/settings.php';
require_once OHHOL_PATH . 'includes/shortcodes.php';
require_once OHHOL_PATH . 'includes/schema.php';

/**
 * Admin assets (settings page only).
 */
function ohhol_admin_assets($hook) {
    if ($hook !== 'toplevel_page_ohhol-settings') {
        return;
    }

    wp_enqueue_style('ohhol-admin', OHHOL_URL . 'admin/admin.css', [], OHHOL_VERSION);
    wp_enqueue_script('ohhol-admin', OHHOL_URL . 'admin/admin.js', ['jquery'], OHHOL_VERSION, true);
    wp_localize_script('ohhol-admin', 'ohholAdmin', [
        'statusClosed'    => __('Closed', 'opening-hours-holidays'),
        'statusModified'  => __('Modified hours', 'opening-hours-holidays'),
        'notePlaceholder' => __('e.g. Public holiday', 'opening-hours-holidays'),
        'removeLabel'     => __('Remove entry', 'opening-hours-holidays'),
    ]);
}
add_action('admin_enqueue_scripts', 'ohhol_admin_assets');

/**
 * Activation: migrate data from the previous "Öffnungszeiten" plugin (oz_* options).
 */
function ohhol_activate() {
    ohhol_maybe_migrate_legacy_options();
}
register_activation_hook(__FILE__, 'ohhol_activate');

/**
 * One-time migration of legacy oz_* options to the new ohhol_* format.
 */
function ohhol_maybe_migrate_legacy_options() {
    if (get_option('ohhol_hours') !== false || get_option('oz_oeffnungszeiten') === false) {
        return;
    }

    $day_map = [
        'montag'     => 'monday',
        'dienstag'   => 'tuesday',
        'mittwoch'   => 'wednesday',
        'donnerstag' => 'thursday',
        'freitag'    => 'friday',
        'samstag'    => 'saturday',
        'sonntag'    => 'sunday',
    ];

    $legacy_hours = (array) get_option('oz_oeffnungszeiten', []);
    $hours        = [];
    foreach ($day_map as $old => $new) {
        if (!isset($legacy_hours[$old])) {
            continue;
        }
        $day = $legacy_hours[$old];
        $hours[$new] = [
            'open'   => !empty($day['offen']) ? 1 : 0,
            'open1'  => $day['von1'] ?? '',
            'close1' => $day['bis1'] ?? '',
            'open2'  => $day['von2'] ?? '',
            'close2' => $day['bis2'] ?? '',
        ];
    }
    if ($hours) {
        update_option('ohhol_hours', $hours);
    }

    $legacy_special = (array) get_option('oz_oeffnungszeiten_spezial', []);
    $holidays       = [];
    foreach ($legacy_special as $entry) {
        if (empty($entry['datum'])) {
            continue;
        }
        $holidays[] = [
            'date'   => $entry['datum'],
            'status' => (isset($entry['status']) && $entry['status'] === 'geaendert') ? 'modified' : 'closed',
            'open1'  => $entry['von1'] ?? '',
            'close1' => $entry['bis1'] ?? '',
            'open2'  => $entry['von2'] ?? '',
            'close2' => $entry['bis2'] ?? '',
            'note'   => $entry['bemerkung'] ?? '',
        ];
    }
    if ($holidays) {
        update_option('ohhol_holidays', $holidays);
    }

    $legacy_options = (array) get_option('oz_oeffnungszeiten_optionen', []);
    update_option('ohhol_options', [
        'show_status'    => !empty($legacy_options['jetzt_offen']) ? 1 : 0,
        'schema_enabled' => 0,
        'schema_name'    => '',
    ]);

    $legacy_design = (array) get_option('oz_oeffnungszeiten_design', []);
    if ($legacy_design) {
        $design_map = [
            'heute_badge_bg'    => 'today_bg',
            'heute_badge_text'  => 'today_text',
            'zebra_color'       => 'zebra_color',
            'zebra_enabled'     => 'zebra_enabled',
            'text_muted'        => 'text_muted',
            'text_faint'        => 'text_faint',
            'geschlossen_farbe' => 'closed_color',
            'offen_bg'          => 'open_bg',
            'offen_text'        => 'open_text',
            'offen_dot'         => 'open_dot',
            'zu_bg'             => 'closed_bg',
            'zu_text'           => 'closed_text',
            'zu_dot'            => 'closed_dot',
        ];
        $design = [];
        foreach ($design_map as $old => $new) {
            if (isset($legacy_design[$old])) {
                $design[$new] = $legacy_design[$old];
            }
        }
        update_option('ohhol_design', $design);
    }

    delete_option('oz_oeffnungszeiten');
    delete_option('oz_oeffnungszeiten_spezial');
    delete_option('oz_oeffnungszeiten_optionen');
    delete_option('oz_oeffnungszeiten_design');
}
