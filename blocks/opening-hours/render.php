<?php
/**
 * Server-side rendering of the "Opening Hours" block.
 *
 * @var array $attributes Block attributes.
 */

if (!defined('ABSPATH')) {
    exit;
}

$ohhol_html = ohhol_render_block_view($attributes);
if ($ohhol_html === '') {
    return;
}

printf(
    '<div %s>%s</div>',
    get_block_wrapper_attributes(), // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by core.
    $ohhol_html // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the shortcode renderers.
);
