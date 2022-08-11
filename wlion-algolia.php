<?php

/**
 * Plugin Name:       White Lion: Agolia Integration
 * Description:       Algolia indexing integration.
 * Version:           1.1.3
 * Author:            White Lion
 * Author URI:        https://www.wlion.com/
 * Plugin URI:        https://www.wlion.com/
 * Text Domain:       wlion-algolia.
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/*
 * Current plugin version.
 */
define('WLION_ALGOLIA_VERSION', '1.1.3');

/**
 * The core plugin class that is used to define admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-wlion-algolia.php';
require_once __DIR__ . '/api-client/autoload.php';

/**
 * Execution of the plugin.
 */
function wl_run_algolia() {
    $plugin = new WlionAlgolia();
    $plugin->run();
}
wl_run_algolia();
