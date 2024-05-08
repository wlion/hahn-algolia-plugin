<?php
/**
 * Custom Hooks for Admin-side of the plugin.
 */
class HahnAlgoliaAdminSearchablePosts {
    /**
     * Custom hooks.
     *
     * @var HahnAlgolia
     */
    private $plugin;

    /**
     * Algolia.
     *
     * @var Algolia
     */
    private $algolia;

    /**
     * Custom hooks.
     *
     * @var HahnAlgoliaSettings
     */
    private $settings;

    /**
     * Option prefix in db.
     *
     * @var string
     */
    private $option_name;

    /**
     * Custom hooks.
     *
     * @var HahnAlgoliaHelpers
     */
    private $helpers;

    /**
     * Initialize the class and set its properties.
     */
    public function __construct(HahnAlgolia $plugin) {
        $this->plugin      = $plugin;
        $this->algolia     = $this->plugin->get_algolia_plugin();
        $this->settings    = $this->plugin->get_settings();
        $this->option_name = $this->plugin->get_option_name();
        $this->helpers     = $this->plugin->get_helpers();
    }

    /**
     * Save searchable posts data.
     *
     * @return string
     */
    public function save_searchable_posts_data($data) {
        $data = maybe_serialize($data);

        try {
            return update_option($this->option_name . 'searchable_posts_data', $data);
        } catch (Exception $e) {
            return $e;
        }
    }

    /**
     * Get searchable posts data.
     *
     * @return array
     */
    private function get_searchable_posts_data() {
        return $this->settings->get_searchable_posts_data();
    }

    /**
     * Should post type be checked for searchable posts in Searchable Posts tab on page-load.
     *
     * @param string $slug
     *
     * @return bool
     */
    public function should_post_type_be_checked($slug) {
        $data = $this->get_searchable_posts_data();

        if (!$data || !count($data)) {
            return null;
        }

        if (is_array($data)) {
            return in_array($slug, $data);
        }

        return $slug === $data;
    }

    /**
     * The post index function.
     *
     * @return void|array
     */
    public function create_records_from_selected_post_types() {
        $records = [];

        // Get all post_types that are selected
        $post_types = $this->get_searchable_posts_data();

        if (!count($post_types)) {
            return;
        }

        // Get posts for indexing
        $posts = $this->get_posts_for_indexing($post_types);

        // Loop over posts
        foreach ($posts as $post) {
            // If post is not published or has password, don't index (also allows for hooking into)
            if (!$this->helpers->should_index($post)) {
                continue;
            }

            $record         = $this->create_index_record($post);
            $prepped_record = $this->helpers->prep_content_for_record($record, $post);

            foreach ($prepped_record as $record) {
                array_push($records, $record);
            }
        }

        return $records;
    }

    /**
     * Separate function to get posts for indexing. This function calls itself recursively
     * as long as the post count is less than the total number of found posts. This method is
     * used to avoid a single query that is too large and which causes `update_meta_cache` to
     * store empty values for posts that we know should have real values.
     *
     * @param array $post_types the post types to include in the WP_Query
     * @param int   $paged      the page at which to being the WP_Query
     * @param array $posts      an array of posts returned from a WP_Query
     *
     * @return void|array
     */
    private function get_posts_for_indexing($post_types = [], $paged = 1, $posts = []) {
        // Bail early if we don't have any post types.
        if (!$post_types) {
            return;
        }

        // Create the query.
        $query = new WP_Query([
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'paged'          => $paged,
        ]);

        // If there are still more posts, recursively call this function until we are done.
        if (count($posts) < $query->found_posts) {
            $posts = array_merge($posts, $this->get_posts_for_indexing($post_types, $paged + 1, $query->posts));
        }

        return $posts;
    }

    /**
     * Create Record from WP Post.
     *
     * @param WP_Post $post
     *
     * @return array
     */
    private function create_index_record($post) {
        $record = [];

        // Get post type label
        $post_type = get_post_type_object($post->post_type);
        if (null === $post_type) {
            throw new RuntimeException('Unable to fetch the post type information.');
        }

        $record['post_type_label']     = $post_type->labels->name;
        $record['post_title']          = $post->post_title;
        $record['post_type']           = $post->post_type;
        $record['post_id']             = $post->ID;
        $record['post_excerpt']        = get_the_excerpt($post->ID);
        $record['post_date']           = get_post_time('U', false, $post);
        $record['post_date_formatted'] = get_the_date('', $post);
        $record['post_modified']       = get_post_modified_time('U', false, $post);
        $record['comment_count']       = (int)$post->comment_count;
        $record['menu_order']          = (int)$post->menu_order;
        $record['images']              = $this->helpers->get_post_images($post->ID);
        $record['permalink']           = get_permalink($post);
        $record['post_mime_type']      = $post->post_mime_type;
        $record['is_sticky']           = is_sticky($post->ID) ? 1 : 0;

        // Get post author data
        $author = get_userdata($post->post_author);
        if ($author) {
            $record['post_author'] = [
                'user_id'      => (int)$post->post_author,
                'display_name' => $author->display_name,
                'user_url'     => $author->user_url,
                'user_login'   => $author->user_login,
            ];
        }

        // Add $record['taxonomies'] and $record['taxonomies_hierarchal']
        $taxonomies = $this->helpers->get_post_taxonomies($post->ID, get_object_taxonomies($post->post_type, 'objects'));
        if ($taxonomies) {
            $record = array_merge($record, $taxonomies);
        }

        // Allow for hooking into
        $record = (array)apply_filters('algolia_searchable_post_shared_attributes', $record, $post);
        $record = (array)apply_filters('algolia_searchable_post_' . $post->post_type . '_shared_attributes', $record, $post);

        return $record;
    }

    /**
     * Push records to {...}_searchable_posts index on algolia.
     *
     * @param bool  $create_settings
     * @param array $records
     */
    public function push_records_to_algolia($create_settings, $records) {
        $response = '';

        // Set index name
        $index = $this->settings->get_searchable_posts_index_name();

        // Init client w/ index
        $client = $this->algolia->initIndex($index);

        // Clear index (leave settings intact), wait for it to finish
        $client->clearObjects()->wait();

        if ($create_settings) {
            // Set default settings
            $update_settings = $client->setSettings([
                'attributeForDistinct' => 'post_id',
                'distinct'             => true,
                'hitsPerPage'          => 20,
                'searchableAttributes' => [
                    'post_title',
                    'unordered(taxonomies)',
                    'post_excerpt',
                    'unordered(content)',
                ],
                'ranking' => [
                    'typo',
                    'geo',
                    'words',
                    'filters',
                    'proximity',
                    'attribute',
                    'exact',
                    'custom',
                ],
                'customRanking' => [
                    'desc(is_sticky)',
                    'desc(post_date)',
                    'asc(record_index)',
                ],
                'attributesToSnippet' => [
                    'content:30',
                    'post_title:30',
                ],
                'attributesForFaceting' => [
                    'post_author.display_name',
                    'post_type_label',
                    'taxonomies',
                    'taxonomies_hierarchal',
                ],
            ]);

            if ($update_settings['updatedAt']) {
                $response = 'Default configuration settings for <strong>' . $index . '</strong> successfully written to Algolia. Please refresh the page.';
            }
        } else {
            $response = 'Configuration settings not overwritten.';
        }

        // Write records to index
        if (count($records) < 1000) {
            $client->saveObjects($records);
        } else {
            do {
                $client->saveObjects(array_splice($records, 0, 999));
            } while (count($records));
        }

        $response = $index . ' successfully re-indexed. ' . $response;

        return $response;
    }

    /**
     * Push/Update single record to {...}_searchable_posts index on algolia.
     *
     * @param array $record
     */
    public function push_single_record_to_algolia($record) {
        // Set index name
        $index = $this->settings->get_searchable_posts_index_name();

        // Init client w/ index
        $client = $this->algolia->initIndex($index);

        // Write to algolia
        $client->saveObjects($record);
    }

    /**
     * On-save-post handler: update record in index.
     *
     * @param int     $post_id
     * @param WP_POST $post
     * @param bool    $update
     *
     * @return string
     */
    public function on_save_post($post_id, $post, $update) {
        $records = [];

        if ($this->helpers->should_index($post) && $this->should_post_type_be_checked($post->post_type)) {
            $record         = $this->create_index_record($post);
            $prepped_record = $this->helpers->prep_content_for_record($record, $post);

            foreach ($prepped_record as $record) {
                array_push($records, $record);
            }

            $this->push_single_record_to_algolia($records);
        }
    }

    /**
     * Post-status-change listener.
     *
     * @param int $post_id
     *
     * @return void
     */
    public function on_post_status_change($post_id) {
        $objectIds = [];
        $i         = 0;
        $index     = $this->settings->get_searchable_posts_index_name();
        $client    = $this->algolia->initIndex($index);

        // Very inelegant, but to be sure all 'chunks' are deleted, if present.
        do {
            array_push($objectIds, (string)"{$post_id}-{$i}");
            ++$i;
        } while ($i < 5);

        // Remove records from index.
        $client->deleteObjects($objectIds);
    }
}
