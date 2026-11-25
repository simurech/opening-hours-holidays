<?php
/**
 * Dependencies of the block editor script (written by hand, no build step).
 */

if (!defined('ABSPATH')) {
    exit;
}

return [
    'dependencies' => ['wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render'],
    'version'      => '2.1.0',
];
