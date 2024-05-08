<?php

/**
 * Provide admin area view for the plugin.
 */
$env = $this->get_environment();
?>

<div class="wrap">
    <h1><?= get_admin_page_title(); ?></h1>
    <?php if (!$this->is_api_key_valid()): ?>
    <div class="notice notice-error inline">
        <p>Could not connect to Algolia. <br>Please check <strong>Application ID</strong> and/or <strong>Admin API
                Key</strong>.</p>
    </div>
    <?php endif; ?>

    <h2 class="nav-tab-wrapper" style="margin: 0 0 1rem">
        <button class="nav-tab nav-tab-active" data-tab-target="api-settings">API Settings</button>
        <?php if ($this->is_api_key_valid()):?>
        <button class="nav-tab" data-tab-target="all-indices">Indices</button>
        <button class="nav-tab" data-tab-target="searchable-posts">Searchable Posts</button>
        <button class="nav-tab" data-tab-target="custom-hooks">Custom Hooks</button>
        <?php endif; ?>
    </h2>

    <div id="hahn-algolia__notice">
        <p></p>
        <button class="hahn-algolia__notice-close"></button>
    </div>

    <div data-tab="api-settings" class="tab-content tab-content-active">
        <form action="options.php" method="post">
            <?php
            settings_fields($this->plugin_slug);
do_settings_sections($this->plugin_slug);
submit_button();
?>
        </form>
    </div>

    <?php if ($this->is_api_key_valid()):?>
    <div data-tab="all-indices" class="tab-content">
        <h2>Algolia Indices</h2>
        <p>
            <label for="toggle-all-indices">
                <input name="toggle-all-indices" type="checkbox" id="toggle-all-indices" />
                <span>Show all indices (regardless of environment)</span>
            </label>
        </p>
        <?php $indices = $this->get_all_indices(); ?>
        <table class="widefat">
            <thead>
                <tr class="alternate">
                    <th class="row-title">Index Name</th>
                    <th class="row-title" style="text-align:right;">Records</th>
                    <th class="row-title">Last Updated</th>
                    <th class="row-title">Settings</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($indices as $k => $index): ?>
                <?php
            $table_row_class = !($this->is_index_for_current_environment($index['name'])) ? 'not-current-env ' : '';
                    $table_row_class .= (0 !== $k % 2) ? 'alternate' : '';
                    ?>

                <tr class="<?= $table_row_class; ?>">
                    <td class="row-title">
                        <?= $index['name']; ?>
                    </td>
                    <td style="text-align:right;">
                        <?= $index['entries']; ?>
                    </td>
                    <td><?= date('Y-M-d H:i:s', strtotime($index['updatedAt'])); ?>
                    </td>
                    <td>
                        <?php if ($this->is_index_for_current_environment($index['name'])): ?>
                        <?php if ($this->get_searchable_post_index() === $index['name']): ?>
                        <button class="button button-secondary" data-tab-target="searchable-posts">
                            Go to Searchable Posts
                        </button>
                        <?php else: ?>
                        <button class="button button-primary get-index-settings" data-retrieved="false"
                            data-index="<?= $index['name']; ?>"
                            data-has-replicas="<?= array_key_exists('replicas', $index) ? 'true' : 'false' ?>">
                            Get Index Settings
                        </button>
                        <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr class="<?= (0 !== $k % 2) ? 'alternate ' : ''; ?>index-settings"
                    data-index="<?= $index['name']; ?>">
                    <td colspan="5">
                        <div class="spinner is-active"></div>
                    </td>
                </tr>
                <?php if (array_key_exists('replicas_data', $index)): ?>
                <?php foreach ($index['replicas_data'] as $replica): ?>
                <tr class="<?= $table_row_class; ?> replica-row">
                    <td class="replica-title">
                        <em><?= $replica['name']; ?></em>
                    </td>
                    <td style="text-align:right;">
                        <?= $replica['entries']; ?>
                    </td>
                    <td><?= date('Y-M-d H:i:s', strtotime($replica['updatedAt'])); ?>
                    </td>
                    <td>
                        <?php if ($this->is_index_for_current_environment($index['name'])): ?>
                        <button class="button button-primary get-index-settings" data-retrieved="false"
                            data-index="<?= $replica['name']; ?>">
                            Get Index Settings
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr class="<?= (0 !== $k % 2) ? 'alternate ' : ''; ?>index-settings"
                    data-index="<?= $replica['name']; ?>">
                    <td colspan="5">
                        <div class="spinner is-active"></div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div data-tab="searchable-posts" class="tab-content">
        <?php $searchable_posts = $this->get_searchable_post_index(); ?>
        <h2>Create/change <em>Searchable Posts</em> index</h2>
        <p style="max-width: 750px;">Note: These settings will only affect
            <strong><em><?= $searchable_posts; ?></em></strong>. By
            clicking <strong>Index Searchable Posts</strong>, you are overwriting any records currently index by Algolia
            for this index, you can not undo this.</p>

        <div class="searchable-posts__post-types">
            <div class="searchable-posts__post-types__settings">
                <h4 class="wl-h4">Configuration Settings:</h4>
                <?php if ($this->does_index_exist($searchable_posts)):
                    $index_settings = $this->get_index_settings($searchable_posts);
                    ?>
                <form class="index-settings-form searchable-posts"
                    data-index-form="<?= $searchable_posts ?>">
                    <table class="form-table" role="presentation">
                        <tbody>
                            <?php foreach ($index_settings as $key_name => $setting): ?>
                            <?php $setting = is_array($setting) ? implode(', ', $setting) : $setting; ?>
                            <tr>
                                <th scope="row">
                                    <label
                                        for="<?= $key_name; ?>"><?= $key_name; ?></label>
                                </th>
                                <td style="padding-right:0;">
                                    <input type="text"
                                        name="<?= $key_name; ?>"
                                        id="<?= $key_name; ?>"
                                        class="large-text code"
                                        value="<?= $setting; ?>">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <br>
                    <button type="submit" class="button button-secondary push-settings"
                        data-index-form="<?= $searchable_posts ?>">
                        Push Settings Changes to Algolia
                    </button>
                </form>
                <?php else: ?>
                <p class="description">(Create Searchable Posts index, and refresh the page, to load default
                    configuration settings.)</p>
                <?php endif; ?>
            </div>

            <div class="searchable-posts__post-types__checkboxes">
                <h4 class="wl-h4">Include Post Types:</h4>
                <?php foreach ($this->get_all_post_types() as $post_type): ?>
                <div class="searchable-posts__post-type__checkbox-container">
                    <input type="checkbox"
                        name="<?= $post_type->name; ?>"
                        id="<?= $post_type->name; ?>"
                        class="searchable-posts__post-type__toggle"
                        value="<?= $post_type->name; ?>"
                        data-post-count="<?= $this->get_searchable_post_types_count([$post_type->name]) ?>"
                        <?= ($this->should_post_type_be_checked($post_type->name)) ? 'checked' : ''; ?>
                    />
                    <label for="<?= $post_type->name; ?>"
                        class="searchable-posts__post-type__label">
                        <?= $post_type->labels->name; ?>
                        (<?= $this->get_searchable_post_types_count([$post_type->name]) ?>)
                    </label>
                </div>
                <?php endforeach; ?>
                <br>
                <button class="button button-secondary save-searchable-posts-types">
                    Save Searchable Post Types
                </button>
            </div>

            <div class="searchable-posts__post-types__count">
                <h4 class="wl-h4">Index:</h4>

                <h3 style="display:inline-block; margin-top:0;">Index size:</h3>
                <div class="searchable-posts__post-types__amount-container">
                    <h3 class="searchable-posts__post-types__amount">
                        <?= $this->get_searchable_post_types_count($this->settings->get_searchable_posts_data()); ?>
                        Posts
                    </h3>
                    <div class="spinner amount-spinner"></div>
                </div>
                <br><br>
                <div class="searchable-posts__post-types__btn-container">
                    <div class="spinner"></div>
                    <button class="button button-primary index-searchable-posts"
                        data-create-settings="<?= $this->does_index_exist($searchable_posts) ? 'false' : 'true' ?>">
                        <?= $this->does_index_exist($searchable_posts) ? 'Re-Index' : 'Create' ?>
                        Searchable Posts
                    </button>
                    <p class="description index-searchable-posts__note">Save Searchable Post Types first.</p>
                </div>
            </div>
        </div>
    </div>

    <div data-tab="custom-hooks" class="tab-content">
        <h2>Custom Hooks</h2>
        <p>Find these hooks in
            <strong><?= $this->plugin->get_custom_hooks_location(); ?></strong>.
        </p>
        <table class="widefat">
            <thead>
                <tr class="alternate">
                    <th class="row-title">Hook</th>
                    <th class="row-title">Description</th>
                    <th class="row-title">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($this->get_custom_hooks() as $hook): ?>
                <tr class="custom-hooks__tr">
                    <td><code><?= $hook['hook_name']; ?></code>
                    </td>
                    <td><?= $hook['description']; ?>
                    </td>
                    <td class="custom-hooks__action-td">
                        <button class="button button-primary hook-trigger"
                            data-hook="<?= $hook['hook_name']; ?>"
                            data-inject-algolia="<?= $hook['inject_algolia']; ?>">
                            <?= $hook['cta_text']; ?>
                        </button>
                        <div class="spinner"
                            data-hook="<?= $hook['hook_name']; ?>">
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>