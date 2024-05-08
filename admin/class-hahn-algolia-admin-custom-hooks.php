<?php
/**
 * Custom Hooks for Admin-side of the plugin.
 */
class HahnAlgoliaAdminCustomHooks {
    /**
     * Custom hooks.
     *
     * @var HahnAlgolia
     */
    private $plugin;

    /**
     * Custom hooks.
     *
     * @var array
     */
    private $custom_hooks;

    /**
     * Initialize the class and set its properties.
     */
    public function __construct(HahnAlgolia $plugin) {
        $this->plugin       = $plugin;
        $this->custom_hooks = $this->get_custom_hooks_in_theme_folder();
    }

    /**
     * Return custom hooks array.
     *
     * @return array
     */
    public function get_custom_hooks() {
        return $this->custom_hooks;
    }

    /**
     * Get Custom Hooks from theme directory ('/hahn/algolia/admin-custom-hooks.php').
     *
     * @return array
     */
    public function get_custom_hooks_in_theme_folder() {
        $custom_hooks_file = $this->plugin->get_custom_hooks_location();

        if (file_exists($custom_hooks_file)) {
            $custom_hooks_file = include $custom_hooks_file;
            if (is_array($custom_hooks_file)) {
                return $custom_hooks_file;
            }
        }

        return [];
    }
}
