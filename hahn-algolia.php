<?php

/**
 * Plugin Name:       Hahn Agency: Agolia Integration
 * Description:       Algolia indexing integration.
 * Version:           1.1.3
 * Author:            Hahn Agency
 * Author URI:        https://hahn.agency
 * Plugin URI:        https://hahn.agency
 * Text Domain:       hahn-algolia.
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/*
 * Current plugin version.
 */
define('HAHN_ALGOLIA_VERSION', '1.1.3');

/**
 * The core plugin class that is used to define admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-hahn-algolia.php';
require_once __DIR__ . '/api-client/autoload.php';

/**
 * Execution of the plugin.
 */
function wl_run_algolia() {
    $plugin = new HahnAlgolia();
    $plugin->run();
}
wl_run_algolia();
