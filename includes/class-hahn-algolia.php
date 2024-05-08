<?php

/**
 * The core plugin class.
 */
class HahnAlgolia {
    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @var HahnAlgoliaLoader
     */
    protected $loader;

    /**
     * To retrieve plugin settings.
     *
     * @var HahnAlgoliaSettings
     */
    protected $settings;

    /**
     * Helper functions.
     *
     * @var HahnAlgoliaHelpers
     */
    protected $helpers;

    /**
     * The Algolia Plugin.
     *
     * @var class
     */
    public $algolia;

    /**
     * Plugin name (slug).
     *
     * @var string
     */
    protected $plugin_slug;

    /**
     * The current version of the plugin.
     *
     * @var string
     */
    protected $version;

    /**
     * The option name to be used in this plugin (ie. prefix in options table).
     *
     * @var string
     */
    protected $option_name = 'wl_algolia_';

    /**
     * File path of custom_hooks file in /themes/hahn/.
     *
     * @var string
     */
    protected $custom_hooks_location;

    /**
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     */
    public function __construct() {
        $this->load_dependencies();

        $this->version               = (defined('HAHN_ALGOLIA_VERSION')) ? HAHN_ALGOLIA_VERSION : '1.0.0';
        $this->plugin_slug           = 'hahn-algolia';
        $this->custom_hooks_location = get_template_directory() . '/algolia/admin-custom-hooks.php';

        $this->initialize_algolia();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * - HahnAlgoliaLoader. Orchestrates the hooks of the plugin.
     * - HahnAlgoliaAdmin.  Defines hooks for the admin area.
     * - HahnAlgoliaPublic. Defines hooks for the public side of the site.
     */
    private function load_dependencies() {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-hahn-algolia-loader.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-hahn-algolia-settings.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-hahn-algolia-helpers.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-hahn-algolia-admin.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-hahn-algolia-public.php';

        $this->loader   = new HahnAlgoliaLoader();
        $this->settings = new HahnAlgoliaSettings($this->get_option_name());
        $this->helpers  = new HahnAlgoliaHelpers();
    }

    /**
     * Initialize Algolia.
     */
    private function initialize_algolia() {
        global $algolia;

        if ($this->settings->get_app_id() && $this->settings->get_admin_api_key()) {
            $this->algolia = Algolia\AlgoliaSearch\SearchClient::create(
                $this->settings->get_app_id(),
                $this->settings->get_admin_api_key()
            );
        } else {
            $this->algolia = null;
        }

        $algolia = $this->algolia;
    }

    /**
     * Register admin-related hooks.
     */
    private function define_admin_hooks() {
        $plugin_admin = new HahnAlgoliaAdmin($this);

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_admin_style');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_admin_script');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_options_page');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_setting');
        $this->loader->add_action('save_post', $plugin_admin, 'save_post_listener', 10, 3);
        $this->loader->add_action('transition_post_status', $plugin_admin, 'post_status_listener', 10, 3);
    }

    /**
     * Register public-related hooks.
     */
    private function define_public_hooks() {
        $plugin_public = new HahnAlgoliaPublic($this);
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_util_script');
        $this->loader->add_action('wp_head', $plugin_public, 'localize_vars');
    }

    /**
     * Run the loader to execute all hooks.
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * Plugin slug.
     *
     * @return string
     */
    public function get_plugin_slug() {
        return $this->plugin_slug;
    }

    /**
     * Loader class reference.
     *
     * @return HahnAlgoliaLoader
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Helpers class reference.
     *
     * @return HahnAlgoliaHelpers
     */
    public function get_helpers() {
        return $this->helpers;
    }

    /**
     * Algolia class reference.
     *
     * @return Algolia
     */
    public function get_algolia_plugin() {
        return $this->algolia;
    }

    /**
     * Settings class reference.
     *
     * @return HahnAlgoliaSettings
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Get version number.
     *
     * @return string
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Get option_name.
     *
     * @return string
     */
    public function get_option_name() {
        return $this->option_name;
    }

    /**
     * Get custom_hooks file location.
     *
     * @return string
     */
    public function get_custom_hooks_location() {
        return $this->custom_hooks_location;
    }
}
