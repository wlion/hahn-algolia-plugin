<?php
/**
 * The public-facing functionality of the plugin.
 */
class HahnAlgoliaPublic {
    /**
     * This plugin.
     *
     * @var HahnAlgolia
     */
    private $plugin;

    /**
     * Initialize class and set template loader listener.
     *
     * @param HahnAlgolia
     */
    public function __construct(HahnAlgolia $plugin) {
        $this->plugin = $plugin;

        // Listen for native templates to override search.php with /agolia/instantsearch.php
        add_filter('template_include', [$this, 'template_loader']);
    }

    /**
     * Localize config vars, for use in JS.
     *
     * @return void
     */
    public function localize_vars() {
        $settings = $this->plugin->get_settings();
        $config   = [
            'application_id' => $settings->get_app_id(),
            'search_api_key' => $settings->get_search_api_key(),
            'query'          => isset($_GET['s']) ? wp_unslash($_GET['s']) : '',
            'indices'        => [
                'searchable_posts' => [
                    'name' => $settings->get_index_prefix() . 'searchable_posts',
                    'id'   => 'searchable_posts',
                ],
            ],
        ];
        print '<script type="text/javascript">var wlAlgolia = ' . json_encode($config) . '</script>';
    }

    /**
     * Load wp-util, which includes underscore.js.
     *
     * @return void
     */
    public function enqueue_util_script() {
        // Make sure wp-util gets loaded, as it includes underscore.js which we need for the hits-template.
        wp_enqueue_script('wp-util');
    }

    /**
     * Load template in /themes/hahn/algolia/ to override default search page.
     *
     * @return string
     */
    public function template_loader($template) {
        // Obviously, only overwrite template when default search page is called
        if (is_search()) {
            return get_template_directory() . '/algolia/instantsearch.php';
        }

        return $template;
    }
}
