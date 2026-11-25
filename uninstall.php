<?php
/**
 * Uninstall: remove all plugin data (on every site of a multisite network).
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete the plugin's options on the current site.
 */
function ohhol_uninstall_site(): void {
    $options = [
        'ohhol_hours',
        'ohhol_holidays',
        'ohhol_options',
        'ohhol_design',
        // Legacy options from the pre-2.0 "Öffnungszeiten" plugin.
        'oz_oeffnungszeiten',
        'oz_oeffnungszeiten_spezial',
        'oz_oeffnungszeiten_optionen',
        'oz_oeffnungszeiten_design',
    ];
    foreach ($options as $option) {
        delete_option($option);
    }
}

if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $ohhol_site_id) {
        switch_to_blog($ohhol_site_id);
        ohhol_uninstall_site();
        restore_current_blog();
    }
} else {
    ohhol_uninstall_site();
}
