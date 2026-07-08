<?php
/**
 * Uninstall: remove all plugin data.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('ohhol_hours');
delete_option('ohhol_holidays');
delete_option('ohhol_options');
delete_option('ohhol_design');

// Legacy options from the pre-2.0 "Öffnungszeiten" plugin.
delete_option('oz_oeffnungszeiten');
delete_option('oz_oeffnungszeiten_spezial');
delete_option('oz_oeffnungszeiten_optionen');
delete_option('oz_oeffnungszeiten_design');
