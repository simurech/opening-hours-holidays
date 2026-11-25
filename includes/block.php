<?php
/**
 * "Opening Hours" block (dynamic, rendered with the shortcode renderers).
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the block. The frontend style handle must exist before the block type.
 */
function ohhol_register_block(): void {
    ohhol_register_assets();
    register_block_type(OHHOL_PATH . 'blocks/opening-hours');
    wp_set_script_translations('ohhol-opening-hours-editor-script', 'opening-hours-holidays', OHHOL_PATH . 'languages');
}
add_action('init', 'ohhol_register_block');

/**
 * Render a block view with the matching shortcode renderer.
 */
function ohhol_render_block_view(array $attributes): string {
    $view      = $attributes['view'] ?? 'week';
    $title     = (string) ($attributes['title'] ?? '');
    $title_tag = ohhol_sanitize_title_tag($attributes['titleTag'] ?? 'h2');

    switch ($view) {
        case 'today':
            return ohhol_shortcode_today(['status' => !empty($attributes['showStatus']) ? 1 : 0]);
        case 'holidays':
            return ohhol_shortcode_holidays([
                'title'     => $title,
                'title_tag' => $title_tag,
                'days'      => max(1, (int) ($attributes['days'] ?? 30)),
                'limit'     => (int) ($attributes['limit'] ?? 0) > 0 ? (int) $attributes['limit'] : -1,
            ]);
        case 'compact':
            return ohhol_shortcode_compact([
                'title'     => $title,
                'title_tag' => $title_tag,
                'status'    => !empty($attributes['showStatus']) ? 1 : 0,
            ]);
        default:
            return ohhol_shortcode_week([
                'title'     => $title,
                'title_tag' => $title_tag,
            ]);
    }
}
