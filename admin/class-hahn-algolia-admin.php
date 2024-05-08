<?php
/**
 * Admin-specific side of the plugin.
 */
class HahnAlgoliaAdmin {
    /**
     * This plugin.
     *
     * @var HahnAlgolia
     */
    private $plugin;

    /**
     * Plugin name.
     *
     * @var string
     */
    private $plugin_slug;

    /**
     * The Algolia plugin.
     *
     * @var class
     */
    private $algolia;

    /**
     * Plugin version.
     *
     * @var string
     */
    private $version;

    /**
     * Option prefix in db.
     *
     * @var string
     */
    private $option_name;

    /**
     * Current env.
     *
     * @var string
     */
    private $environment;

    /**
     * Plugin version.
     *
     * @var HahnAlgoliaHelpers
     */
    private $helpers;

    /**
     * Custom hooks for the admin side of the plugin.
     *
     * @var HahnAlgoliaAdminCustomHooks
     */
    private $custom_hooks;

    /**
     * Custom hooks for the admin side of the plugin.
     *
     * @var HahnAlgoliaAdminSearchablePosts
     */
    private $searchable_posts;

    /**
     * Store all indices, no need to hit algolia multiple times.
     *
     * @var array
     */
    private $all_indices = [];

    /**
     * Settings.
     *
     * @var HahnAlgoliaSettings
     */
    private $settings;

    /**
     * Initialize the class and set its properties.
     *
     * @param HahnAlgolia $plugin
     */
    public function __construct($plugin) {
        if (is_admin()) {
            ini_set('memory_limit', '512M');
        }

        $this->plugin      = $plugin;
        $this->algolia     = $plugin->get_algolia_plugin();
        $this->helpers     = $plugin->get_helpers();
        $this->plugin_slug = $plugin->get_plugin_slug();
        $this->option_name = $plugin->get_option_name();
        $this->settings    = $plugin->get_settings();
        $this->version     = $plugin->get_version();
        $this->environment = (WP_ENV === 'dev') ? 'local' : strtolower(WP_ENV);

        add_action('wp_ajax_get_index_settings', [$this, 'ajax_get_index_settings']);
        add_action('wp_ajax_push_index_settings', [$this, 'ajax_push_index_settings']);
        add_action('wp_ajax_trigger_custom_hook', [$this, 'ajax_trigger_custom_hook']);
        add_action('wp_ajax_save_searchable_post_types', [$this, 'ajax_save_searchable_post_types']);
        add_action('wp_ajax_get_searchable_post_types', [$this, 'ajax_get_searchable_post_types']);
        add_action('wp_ajax_get_searchable_post_types_count', [$this, 'ajax_get_searchable_post_types_count']);
        add_action('wp_ajax_index_searchable_posts', [$this, 'ajax_index_searchable_posts']);

        $this->load_admin_custom_hooks();
        $this->load_admin_searchable_posts();
    }

    /**
     * Register admin css.
     */
    public function enqueue_admin_style() {
        wp_enqueue_style($this->plugin_slug, plugin_dir_url(__FILE__) . 'css/hahn-algolia-admin.css', [], $this->version, 'all');
    }

    /**
     * Register admin js.
     */
    public function enqueue_admin_script() {
        wp_enqueue_script($this->plugin_slug, plugin_dir_url(__FILE__) . 'js/hahn-algolia-admin.js', ['jquery'], $this->version, false);

        $admin_options = [
            'ajax_url' => admin_url('admin-ajax.php'),
        ];

        wp_localize_script($this->plugin_slug, 'wlAlgoliaAdmin', $admin_options);
    }

    /**
     * On save-post listener.
     *
     * @param int     $post_ID
     * @param WP_Post $post
     * @param bool    $update
     */
    public function save_post_listener($post_ID, $post, $update) {
        return $this->searchable_posts->on_save_post($post_ID, $post, $update);
    }

    /**
     * Post-status-change listener.
     *
     * @param string  $new_status
     * @param string  $old_status
     * @param WP_Post $post
     */
    public function post_status_listener($new_status, $old_status, $post) {
        if ('publish' !== $new_status) {
            $this->searchable_posts->on_post_status_change($post->ID);
        }
    }

    /**
     * Load Custom Hooks class, used for 'Custom Hooks' section of plugin in admin.
     */
    public function load_admin_custom_hooks() {
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-hahn-algolia-admin-custom-hooks.php';
        $this->custom_hooks = new HahnAlgoliaAdminCustomHooks($this->plugin);
    }

    /**
     * Load Searchable Posts class, used for 'Searchable Posts' section of plugin in admin.
     */
    public function load_admin_searchable_posts() {
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-hahn-algolia-admin-searchable-posts.php';
        $this->searchable_posts = new HahnAlgoliaAdminSearchablePosts($this->plugin);
    }

    /**
     * Add an options page under the Settings submenu.
     */
    public function add_options_page() {
        add_options_page(
            'Hahn: Algolia Settings',
            'Hahn: Algolia',
            'manage_options',
            $this->plugin_slug,
            [$this, 'display_options_page']
        );
    }

    /**
     * Outputs admin page.
     */
    public function display_options_page() {
        include_once 'partials/hahn-algolia-admin-display.php';
    }

    /**
     * Settings form fields.
     *
     * @var array
     */
    private $form_fields = [
        [
            'label' => 'Application ID',
            'slug'  => 'app_id',
            'type'  => 'text',
        ],
        [
            'label' => 'Admin API Key',
            'slug'  => 'api_key_admin',
            'type'  => 'password',
        ],
        [
            'label' => 'Search-only API Key',
            'slug'  => 'api_key_search',
            'type'  => 'text',
        ],
        [
            'label'  => 'Index prefix',
            'slug'   => 'index_prefix',
            'type'   => 'text',
            'render' => 'text_field_index_prefix_render',
        ],
    ];

    /**
     * Add an options page under the Settings submenu.
     */
    public function register_setting() {
        add_settings_section(
            $this->option_name . 'settings',  // Section name
            'Algolia API Settings',           // Section title
            [$this, 'settings_render'],       // Render callback
            $this->plugin_slug                // Option page slug
        );

        // register settings input fields by looping over '$this->form_fields'
        foreach ($this->form_fields as $field) {
            $render_function = isset($field['render']) ? $field['render'] : 'text_field_render';
            $input_type      = isset($field['type']) ? $field['type'] : 'text';

            // create field
            add_settings_field(
                $this->option_name . $field['slug'],  // ID
                $field['label'],                      // Title
                [$this, $render_function],            // Callback function that renders field
                $this->plugin_slug,                   // Page slug ('hahn-algolia')
                $this->option_name . 'settings',      // Section name this should live in
                [
                    'label_for' => $this->option_name . $field['slug'], // Extra args
                    'type'      => $input_type,
                    'slug'      => $field['slug'],
                ]
            );

            // register field
            register_setting(
                $this->plugin_slug,                   // Settings group name
                $this->option_name . $field['slug'],  // Option name in db ('wl_algolia_{slug}')
                [
                    'type'              => 'string',
                    'sanitize_callback' => [$this, 'text_field_sanitize'],
                ]
            );
        }
    }

    /**
     * Render the text for the general section.
     */
    public function settings_render() {
        print '<p>You can find these keys in your Algolia profile, under the <strong>API Keys</strong> menu-item.</p>';
    }

    /**
     * Sanitize text form field.
     *
     * @param string $input
     *
     * @return string
     */
    public function text_field_sanitize($input) {
        return filter_var($input, FILTER_SANITIZE_STRING);
    }

    /**
     * Render text form fields.
     *
     * @param array $field
     *
     * @return void
     */
    public function text_field_render($field) { ?>
<input type="<?= $field['type']; ?>"
    name="<?= $field['label_for']; ?>"
    id="<?= $field['label_for']; ?>"
    class="regular-text"
    value="<?= $this->get_data($field['slug']); ?>" />
<?php }

    /**
     * Render 'Index Prefix' text form field w/ description.
     *
     * @param array $field
     *
     * @return void
     */
    public function text_field_index_prefix_render($field) { ?>
<input type="<?= $field['type']; ?>"
    name="<?= $field['label_for']; ?>"
    id="<?= $field['label_for']; ?>"
    class="regular-text"
    value="<?= $this->get_data($field['slug']); ?>" />
<br>
<p class="description">
    This prefix will be prepended to your indices.
    <?php $prefix = $this->get_data($field['slug']); ?>
    <?php if (!strpos($prefix, $this->environment)): ?>
    <br>
    <em style="color:red;">Prefix should contain
        '<strong>_<?= $this->environment; ?>_</strong>'.</em>
    <?php endif; ?>
</p>
<?php }

    /**
     * Get Options data.
     *
     * @param string $suffix
     *
     * @return array
     */
    private function get_data($suffix) {
        return $this->settings->get_data($suffix);
    }

    /**
     * Get API key.
     *
     * @return string
     */
    private function get_api_key() {
        $api_key = $this->settings->get_admin_api_key();

        return $api_key ? $api_key : '0';
    }

    /**
     * Test API key to make sure it's valid.
     *
     * @return bool
     */
    public function is_api_key_valid() {
        if (!$this->algolia) {
            return false;
        }

        try {
            $this->algolia->getApiKey($this->get_api_key());
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Get all indices, regardless of their environment.
     *
     * @return array
     */
    private function get_all_indices() {
        if (count($this->all_indices)) {
            return $this->all_indices;
        }

        $list_indices = $this->algolia->listIndices();
        $primaries    = [];
        $replicas     = [];

        // Sort, and split into replicas and primaries
        foreach ($list_indices['items'] as $index) {
            if (!$index['entries']) {
                continue;
            }

            if (array_key_exists('primary', $index)) {
                array_push($replicas, $index);
            } else {
                array_push($primaries, $index);
            }
        }

        if (!count($this->all_indices)) {
            $this->all_indices = $this->merge_replicas_indices_with_their_primaries($replicas, $primaries);
        }

        return $this->merge_replicas_indices_with_their_primaries($replicas, $primaries);
    }

    /**
     * Get indices for current environment.
     *
     * @return bool|array
     */
    private function get_indices_for_current_environment() {
        $list_indices = $this->algolia->listIndices();
        $prefix       = $this->get_data('index_prefix');
        $primaries    = [];
        $replicas     = [];

        if (!$prefix) {
            return false;
        }

        // sort indices
        foreach ($list_indices['items'] as $index) {
            if (substr($index['name'], 0, strlen($prefix)) !== $prefix || !$index['entries']) {
                continue;
            }

            if (array_key_exists('primary', $index)) {
                array_push($replicas, $index);  // Replica index
            } else {
                array_push($primaries, $index); // Primary index
            }
        }

        return $this->merge_replicas_indices_with_their_primaries($replicas, $primaries);
    }

    /**
     * Merge replica indices with their primary index for easier handling.
     *
     * @param array $replicas
     * @param array $primaries
     *
     * @return array
     */
    private function merge_replicas_indices_with_their_primaries($replicas, $primaries) {
        foreach ($replicas as $replica_index) {
            $primary_name = $replica_index['primary'];

            foreach ($primaries as $k => $primary_index) {
                if ($primary_index['name'] === $primary_name) {
                    if (!array_key_exists('replicas_data', $primary_index)) {
                        $primaries[$k]['replicas_data'] = [];
                    }
                    array_push($primaries[$k]['replicas_data'], $replica_index);
                }
            }
        }

        return $primaries;
    }

    /**
     * Check whether index exists.
     *
     * @param string $index_name
     *
     * @return bool
     */
    private function does_index_exist($index_name) {
        foreach ($this->get_all_indices() as $index) {
            if ($index['name'] === $index_name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get index settings.
     *
     * @param string $index_name
     *
     * @return array
     */
    private function get_index_settings($index_name) {
        $index = $this->algolia->initIndex($index_name);

        return $index->getSettings();
    }

    /**
     * Check whether index is for current environment.
     *
     * @param string $index_name
     *
     * @return bool
     */
    private function is_index_for_current_environment($index_name) {
        return strpos(strtolower($index_name), '_' . $this->environment . '_');
    }

    /**
     * Sort indices by name, current ENV first.
     *
     * @param array $indices
     *
     * @return bool|array
     */
    private function sort_indices($indices) {
        if (!is_array($indices)) {
            return false;
        }

        $currentEnv = [];
        $rest       = [];

        foreach ($indices as $index) {
            if (false !== strpos(strtolower($index['name']), $this->environment)) {
                $currentEnv[] = $index;
            } else {
                $rest[] = $index;
            }
        }

        return array_merge($currentEnv, $rest);
    }

    /**
     * Get all post types available.
     *
     * @return array
     */
    public function get_all_post_types() {
        $exclude_types = ['attachment', 'media'];
        $post_types    = get_post_types([
            'public'              => true,
            'exclude_from_search' => false,
        ],
            'object',
            'and');

        foreach ($exclude_types as $type) {
            unset($post_types[$type]);
        }

        // Sort alphabetically
        usort($post_types, function ($a, $b) {
            return $a->labels->name <=> $b->labels->name;
        });

        return $post_types;
    }

    /**
     * Get searchable posts index for current environment.
     *
     * @return string
     */
    private function get_searchable_post_index() {
        return $this->settings->get_searchable_posts_index_name();
    }

    /**
     * Get Environment.
     *
     * @return string
     */
    private function get_environment() {
        return $this->environment;
    }

    /**
     * Get custom hooks.
     *
     * @return array
     */
    private function get_custom_hooks() {
        return $this->custom_hooks->get_custom_hooks();
    }

    /**
     * Should post type be checked for searchable posts.
     *
     * @param string $slug
     *
     * @return bool
     */
    private function should_post_type_be_checked($slug) {
        return $this->searchable_posts->should_post_type_be_checked($slug);
    }

    /**
     * Get Searchable Posts Count.
     *
     * @param string|array $post_types
     *
     * @return string|int
     */
    private function get_searchable_post_types_count($post_types) {
        if (!$post_types || !count($post_types)) {
            return '0';
        }

        $query = new WP_Query([
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ]);

        $count = 0;

        foreach ($query->posts as $post) {
            if ($this->helpers->should_index($post)) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Ajax: Get Index Settings.
     */
    public function ajax_get_index_settings() {
        $settings = $this->get_index_settings($_POST['index']);
        $response = [
            'success' => false,
            'message' => '',
            'data'    => [],
        ];

        if ($settings) {
            $response['success'] = true;
            $response['message'] = 'Settings successfully retrieved for index.';
            $response['data']    = $settings;
        } else {
            $response['message'] = 'Could not get settings for index.';
        }

        print json_encode($response);
        exit;
    }

    /**
     * Ajax: Push index settings to Algolia.
     */
    public function ajax_push_index_settings() {
        $index               = $_POST['index'];
        $form_data           = $_POST['formData'];
        $forward_to_replicas = false;
        $response            = [
            'success' => false,
            'message' => '',
        ];
        $should_be_array_type = [
            'attributesToRetrieve',
            'unretrievableAttributes',
            'numericAttributesForFiltering',
            'numericAttributesToIndex',
            'attributes',
            'attributesToHighlight',
            'attributesToSnippet',
            'ranking',
            'customRanking',
            'attributesForFaceting',
            'replicas',
        ];

        // Parse form data
        parse_str($form_data, $params);

        // Prepare data for Algolia
        if (!empty($params)) {
            foreach ($params as $key => $param) {
                if (empty($param)) {
                    unset($params[$key]);

                    continue;
                }

                if ('forwardToReplicas' === $key) {
                    $forward_to_replicas = ('on' === $params[$key] ? true : false);
                    unset($params[$key]);

                    continue;
                }

                if (strpos($param, ', ')) {
                    $params[$key] = explode(', ', $param);
                } else {
                    if (in_array($key, $should_be_array_type)) {
                        $params[$key] = [$param];
                    } else {
                        if (is_numeric($param)) {
                            $params[$key] = (int)$param;
                        } else {
                            $params[$key] = $param;
                        }
                    }
                }
            }
        }

        // initialize index
        $client = $this->algolia->initIndex($index);

        // write settings
        try {
            $update = $client->setSettings($params, ['forwardToReplicas' => $forward_to_replicas]);
        } catch (Exception $e) {
            $response['message'] = var_dump($e);
        }

        // if success
        if ($update['updatedAt']) {
            $response['success'] = true;
            $response['message'] = 'Configuration settings for ' . $index . ' successfully written to Algolia ';
            if ($forward_to_replicas) {
                $response['message'] .= 'and forwarded to replicas';
            }
            $response['message'] .= '.';
        }

        print json_encode($response);
        exit;
    }

    /**
     * Ajax: Save Searchable Posts settings.
     */
    public function ajax_save_searchable_post_types() {
        $response = [
            'success'    => false,
            'post_types' => '',
            'message'    => '',
        ];

        $response['success']    = $this->searchable_posts->save_searchable_posts_data($_POST['postTypes']);
        $response['message']    = $response['success'] ? 'Successfully updated indexable post types.' : 'Could not update post types, please try again.';
        $response['post_types'] = $this->settings->get_searchable_posts_data();

        print json_encode($response);
        exit;
    }

    /**
     * Ajax: Get Searchable Posts settings.
     */
    public function ajax_get_searchable_post_types() {
        $response = [
            'success' => true,
            'data'    => $this->settings->get_searchable_posts_data(),
        ];

        print json_encode($response);
        exit;
    }

    /**
     * Ajax: (Re-)Index Searchable Posts.
     */
    public function ajax_index_searchable_posts() {
        $create_settings = 'true' === $_POST['createSettings'] ? true : false;
        $response        = [
            'success' => false,
            'message' => '',
        ];

        $records = $this->searchable_posts->create_records_from_selected_post_types();

        try {
            $response['success'] = true;
            $response['message'] = $this->searchable_posts->push_records_to_algolia($create_settings, $records);
        } catch (Exception $e) {
            var_dump($e);
            exit;
        }

        print json_encode($response);
        exit;
    }

    /**
     * Ajax: Trigger custom hook.
     */
    public function ajax_trigger_custom_hook() {
        $hook           = $_POST['hook'];
        $inject_algolia = (bool)$_POST['inject'];
        $response       = [
            'success'         => false,
            'message'         => '',
            'function_return' => '',
        ];

        if (function_exists($hook)) {
            if ($inject_algolia) {
                $response['function_return'] = call_user_func($hook, $this->algolia);
            } else {
                $response['function_return'] = call_user_func($hook);
            }

            $response['success'] = true;
            $response['message'] = 'Found <em>' . $hook . '</em> and executed it.';
        } else {
            $response['message'] = 'Unable to find function/hook: ' . $hook . '.';
        }

        print json_encode($response);
        exit;
    }
}
?>
