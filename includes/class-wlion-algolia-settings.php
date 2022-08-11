<?php
/**
 * Settings class to retrieve options from database.
 */
class WlionAlgoliaSettings {
    /**
     * The options name to be used in this plugin.
     *
     * @var string
     */
    private $option_name;

    /**
     * Load class.
     */
    public function __construct($option_name) {
        $this->option_name = $option_name;
    }

    /**
     * Get value for specific setting.
     *
     * @return string
     */
    public function get_data($suffix) {
        return get_option($this->option_name . $suffix);
    }

    /**
     * Get app id.
     *
     * @return string
     */
    public function get_app_id() {
        return get_option($this->option_name . 'app_id');
    }

    /**
     * Get admin api key.
     *
     * @return string
     */
    public function get_admin_api_key() {
        return get_option($this->option_name . 'api_key_admin');
    }

    /**
     * Get search-only api key.
     *
     * @return string
     */
    public function get_search_api_key() {
        return get_option($this->option_name . 'api_key_search');
    }

    /**
     * Get index prefix.
     *
     * @return string
     */
    public function get_index_prefix() {
        return get_option($this->option_name . 'index_prefix');
    }

    /**
     * Get searchable posts index name for current env.
     *
     * @return string
     */
    public function get_searchable_posts_index_name() {
        return $this->get_index_prefix() . 'searchable_posts';
    }

    /**
     * Get post types selected to be indexed for searchable_posts index.
     *
     * @return string|array
     */
    public function get_searchable_posts_data() {
        return maybe_unserialize(get_option($this->option_name . 'searchable_posts_data'));
    }
}
