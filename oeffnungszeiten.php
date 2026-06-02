<?php
/**
 * Plugin Name: Öffnungszeiten Plugin
 * Description: Verwalte reguläre und spezielle Öffnungszeiten mit Shortcodes.
 * Version: 1.1.0
 * Author: Simon Urech
 * Author URI: https://urech.dev
 * Text Domain: oeffnungszeiten
 */

if (!defined('ABSPATH')) {
    exit;
}

define('OZ_PLUGIN_VERSION', '1.1.0');
define('OZ_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('OZ_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Plugin-Komponenten laden
 */
function oz_load_plugin() {
    require_once OZ_PLUGIN_PATH . 'includes/settings.php';
    require_once OZ_PLUGIN_PATH . 'includes/shortcodes.php';
}
add_action('plugins_loaded', 'oz_load_plugin');

/**
 * Admin-Styles laden
 */
function oz_admin_styles($hook) {
    if ($hook === 'toplevel_page_oz-oeffnungszeiten') {
        wp_enqueue_style('oz-admin', OZ_PLUGIN_URL . 'admin/admin.css', [], OZ_PLUGIN_VERSION);
    }
}
add_action('admin_enqueue_scripts', 'oz_admin_styles');

/**
 * Aktivierung
 */
function oz_activate() {
    oz_load_plugin();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'oz_activate');

/**
 * Deaktivierung
 */
function oz_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'oz_deactivate');
