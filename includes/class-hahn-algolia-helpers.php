<?php
/**
 * Helper function class.
 */
class HahnAlgoliaHelpers {
    /**
     * Get post images.
     *
     * @param int $post_id
     *
     * @return array
     */
    public function get_post_images($post_id) {
        $images = [];

        if ('attachment' === get_post_type($post_id)) {
            $post_thumbnail_id = (int)$post_id;
        } else {
            $post_thumbnail_id = get_post_thumbnail_id((int)$post_id);
        }

        if ($post_thumbnail_id) {
            $info = wp_get_attachment_image_src($post_thumbnail_id, 'thumbnail');

            if ($info) {
                $images['thumbnail'] = [
                    'url'    => $info[0],
                    'width'  => $info[1],
                    'height' => $info[2],
                ];
            }
        }

        return $images;
    }

    /**
     * Format taxonomies for record.
     *
     * @param WP_Taxonomy $taxonomies
     *
     * @return array
     */
    public function get_post_taxonomies($post_id, $taxonomies) {
        $record                            = [];
        $record['taxonomies']              = [];
        $record['taxonomies_hierarchical'] = [];

        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($post_id, $taxonomy->name);
            $terms = is_array($terms) ? $terms : [];

            if ($taxonomy->hierarchical) {
                $hierarchical_taxonomy_values = $this->get_taxonomy_tree($terms, $taxonomy->name);
                if (!empty($hierarchical_taxonomy_values)) {
                    $record['taxonomies_hierarchical'][$taxonomy->name] = $hierarchical_taxonomy_values;
                }
            }

            $taxonomy_values = wp_list_pluck($terms, 'name');
            if (!empty($taxonomy_values)) {
                $record['taxonomies'][$taxonomy->name] = $taxonomy_values;
            }
        }

        return $record;
    }

    /**
     * (Lifted from Algolia's helper functions).
     *
     * Returns an array like:
     * array(
     *    'lvl0' => ['Sales', 'Marketing'],
     *    'lvl1' => ['Sales > Strategies', 'Marketing > Tips & Tricks']
     *    ...
     * );.
     *
     * This is useful when building hierarchical menus.
     *
     * @see https://community.algolia.com/instantsearch.js/documentation/#hierarchicalmenu
     *
     * @param string $taxonomy
     * @param string $separator
     *
     * @return array
     */
    public function get_taxonomy_tree(array $terms, $taxonomy, $separator = ' > ') {
        $term_ids = wp_list_pluck($terms, 'term_id');

        $parents = [];
        foreach ($term_ids as $term_id) {
            $path      = $this->get_term_parents($term_id, $taxonomy, $separator);
            $parents[] = rtrim($path, $separator);
        }

        $terms = [];
        foreach ($parents as $parent) {
            $levels = explode($separator, $parent);

            $previous_lvl = '';
            foreach ($levels as $index => $level) {
                $terms['lvl' . $index][] = $previous_lvl . $level;
                $previous_lvl .= $level . $separator;

                // Make sure we have not duplicate.
                // The call to `array_values` ensures that we do not end up with an object in JSON.
                $terms['lvl' . $index] = array_values(array_unique($terms['lvl' . $index]));
            }
        }

        return $terms;
    }

    /**
     * (Lifted from Algolia's helper functions).
     *
     * Retrieve term parents with separator.
     *
     * @param int    $id        term ID
     * @param string $taxonomy
     * @param string $separator Optional, default is '/'. How to separate terms.
     * @param bool   $nicename  Optional, default is false. Whether to use nice name for display.
     * @param array  $visited   Optional. Already linked to terms to prevent duplicates.
     *
     * @return string|WP_Error a list of terms parents on success, WP_Error on failure
     */
    public function get_term_parents($id, $taxonomy, $separator = '/', $nicename = false, $visited = []) {
        $chain  = '';
        $parent = get_term($id, $taxonomy);
        if (is_wp_error($parent)) {
            return $parent;
        }

        if ($nicename) {
            $name = $parent->slug;
        } else {
            $name = $parent->name;
        }

        if ($parent->parent && ($parent->parent != $parent->term_id) && !in_array($parent->parent, $visited)) {
            $visited[] = $parent->parent;
            $chain .= $this->get_term_parents($parent->parent, $taxonomy, $separator, $nicename, $visited);
        }

        $chain .= $name . $separator;

        return $chain;
    }

    public function prepare_content($content) {
        return $this->remove_content_noise($content);
    }

    public function remove_content_noise($content) {
        $noise_patterns = [
            // Strip out comments.
            "'<!--(.*?)-->'is",
            // Strip out cdata.
            "'<!\[CDATA\[(.*?)\]\]>'is",
            // Per sourceforge http://sourceforge.net/tracker/?func=detail&aid=2949097&group_id=218559&atid=1044037
            // Script tags removal now preceeds style tag removal.
            // Strip out <script> tags.
            "'<\s*script[^>]*[^/]>(.*?)<\s*/\s*script\s*>'is",
            "'<\s*script\s*>(.*?)<\s*/\s*script\s*>'is",
            // Strip out <style> tags.
            "'<\s*style[^>]*[^/]>(.*?)<\s*/\s*style\s*>'is",
            "'<\s*style\s*>(.*?)<\s*/\s*style\s*>'is",
            // Strip out preformatted tags.
            "'<\s*(?:code)[^>]*>(.*?)<\s*/\s*(?:code)\s*>'is",
            // Strip out <pre> tags.
            "'<\s*pre[^>]*[^/]>(.*?)<\s*/\s*pre\s*>'is",
            "'<\s*pre\s*>(.*?)<\s*/\s*pre\s*>'is",
        ];

        // If there is ET builder (Divi), remove shortcodes.
        if (function_exists('et_pb_is_pagebuilder_used')) {
            $noise_patterns[] = '/\[\/?et_pb.*?\]/';
        }

        $noise_patterns = (array)apply_filters('algolia_strip_patterns', $noise_patterns);

        foreach ($noise_patterns as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }

        // Remove html tags
        $content = strip_tags($content);

        // Remove common words from content, they have no search value
        $common_words = [
            ' the ',
            ' be ',
            ' to ',
            ' of ',
            ' and ',
            ' a ',
            ' in ',
            ' is ',
            ' if ',
            ' that ',
            ' have ',
            ' it ',
            ' for ',
            ' not ',
            ' on ',
            ' or ',
            ' let ',
            ' with ',
            ' as ',
            ' you ',
            ' do ',
            ' at ',
            ' this ',
            ' but ',
            ' by ',
            ' your ',
            ' can ',
        ];

        // ..maybe the word occurs at the end of a sentence.
        foreach ($common_words as $word) {
            array_push($common_words, ' ' . trim($word) . '.');
        }

        // Strip all common words from string
        $content = json_decode(str_replace($common_words, ' ', json_encode(strtolower($content))));

        // Remove new lines and double spaces
        $content = trim(preg_replace('/\s+/', ' ', trim($content)));

        return html_entity_decode($content);
    }

    /**
     * (Lifted from Algolia's helper functions).
     *
     * @param string $content
     *
     * @return array
     */
    public function explode_content($content) {
        $max_size = 2000;
        if (defined('ALGOLIA_CONTENT_MAX_SIZE')) {
            $max_size = (int)ALGOLIA_CONTENT_MAX_SIZE;
        }

        $parts  = [];
        $prefix = '';
        while (true) {
            $content = trim((string)$content);
            if (strlen($content) <= $max_size) {
                $parts[] = $prefix . $content;

                break;
            }

            $offset          = -(strlen($content) - $max_size);
            $cut_at_position = strrpos($content, ' ', $offset);

            if (false === $cut_at_position) {
                $cut_at_position = $max_size;
            }
            $parts[] = $prefix . substr($content, 0, $cut_at_position);
            $content = substr($content, $cut_at_position);

            $prefix = '… ';
        }

        return $parts;
    }

    /**
     * @return bool
     */
    public function should_index($post) {
        return $this->should_index_post($post);
    }

    /**
     * @return bool
     */
    private function should_index_post(WP_Post $post) {
        $should_index = 'publish' === $post->post_status && empty($post->post_password);

        return (bool)apply_filters('algolia_should_index_searchable_post', $should_index, $post);
    }

    /**
     * Create Object ID, and allow for records with multiple chunks.
     *
     * @param int $post_id
     * @param int $record_index
     *
     * @return string
     */
    private function get_post_object_id($post_id, $record_index) {
        return $post_id . '-' . $record_index;
    }

    /**
     * @param array   $record
     * @param WP_Post $post
     *
     * @return array
     */
    public function prep_content_for_record($record, $post) {
        // Turn off WP's autformatting for a sec so we can pull 'unformatted' content..
        $removed = remove_filter('the_content', 'wptexturize', 10);

        // ..get the content and allow for hooking-into..
        $post_content = apply_filters('algolia_searchable_post_content', $post->post_content, $post);
        $post_content = apply_filters('the_content', $post_content);

        // ..and turn back on.
        if (true === $removed) {
            add_filter('the_content', 'wptexturize', 10);
        }

        // Remove clutter from content, leaving only a long string of text
        $post_content = $this->prepare_content($post_content);

        // Split content in chunks of 2000 chars max if content >2000 chars
        $post_content_chunks = $this->explode_content($post_content);

        $this_record = [];
        foreach ($post_content_chunks as $k => $content_chunk) {
            $record                 = $record;
            $record['objectID']     = $this->get_post_object_id($post->ID, $k);
            $record['content']      = $content_chunk;
            $record['record_index'] = $k;
            $this_record[]          = $record;
        }

        // Allow for hooking-into once more before adding to records array..
        $this_record[0] = array_merge($this_record[0], (array)apply_filters('algolia_searchable_post_records', $this_record, $post)[0]);
        $this_record[0] = array_merge($this_record[0], (array)apply_filters('algolia_searchable_post_' . $post->post_type . '_records', $this_record, $post)[0]);

        return $this_record;
    }
}
