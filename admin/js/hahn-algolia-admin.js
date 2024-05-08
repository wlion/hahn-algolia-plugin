(($) => {
  $(document).ready(() => {
    let searchablePostTypes = getSearchablePostTypes();
    const $notice = $('#hahn-algolia__notice');

    /*
     ** Event Handlers
     */
    // Click handler for the Tabs component
    $('[data-tab-target]').on('click', (event) => {
      event.preventDefault();

      const $this = $(event.currentTarget);
      const $thisTab = $(`[data-tab-target=${$this.data('tab-target')}]`);
      const $tabContent = $(`[data-tab=${$this.data('tab-target')}]`);

      $('.nav-tab-active').removeClass('nav-tab-active');
      $('.tab-content-active').hide().removeClass('tab-content-active');

      $thisTab.addClass('nav-tab-active');
      $tabContent.addClass('tab-content-active').show();
    });

    // Close notice
    $(document).on('click', '.hahn-algolia__notice-close', () =>
      $notice.hide()
    );

    // 'Get Index Settings' button click
    $(document).on('click', 'button[data-index]', (event) => {
      event.preventDefault();

      const $this = $(event.currentTarget);
      const index = $this.data('index');
      const hasReplicas = $this.data('has-replicas');

      $(`tr[data-index=${index}]`).show();

      if ($this.attr('data-retrieved') === 'false') {
        // Retrieve index settings
        $.ajax({
          url: wlAlgoliaAdmin.ajax_url,
          type: 'post',
          data: {
            action: 'get_index_settings',
            index: index
          },
          dataType: 'json',
          success: ({ data }) => {
            $this.attr('data-retrieved', 'true').prop('disabled', true);

            $(`tr[data-index=${index}]`)
              .children('td')
              .html(createSettingsForm(data, index, hasReplicas));
          }
        });
      }
    });

    // Enable submit button on input field change
    $(document).on('change', 'input[data-index-form]', (event) => {
      const index = $(event.currentTarget).data('index-form');
      $(`button.push-settings[data-index-form=${index}]`).prop(
        'disabled',
        false
      );
    });

    // Push Index Settings to Algolia
    $(document).on('submit', 'form.index-settings-form', (event) => {
      event.preventDefault();

      const $this = $(event.currentTarget);
      const index = $this.data('index-form');
      const $pushSettingsButton = $(
        `button.push-settings[data-index-form=${index}]`
      );
      const $loader = $(`.spinner[data-index-form=${index}]`);

      // Use 'local' notice:
      const $notice = $(`.notice[data-index-form=${index}]`);

      // Son't submit any empty values, so disable empty fields before posting
      $this.children(':input[value=""]').attr('disabled', 'disabled');

      $loader.addClass('is-active');

      // Send updated settings over ajax
      $.ajax({
        url: wlAlgoliaAdmin.ajax_url,
        type: 'post',
        data: {
          action: 'push_index_settings',
          index: index,
          formData: $this.serialize()
        },
        dataType: 'json',
        success: ({ success, message }) => {
          // Enable empty fields again
          $this.children(':input[value=""]').removeAttr('disabled');

          if ($this.hasClass('searchable-posts')) {
            displayNotice(success, message); // Use 'global' notice for searchable posts form
          } else {
            $loader.removeClass('is-active');
            $pushSettingsButton.prop('disabled', true);
            $notice
              .addClass(`${success ? 'notice-success' : 'notice-error'} inline`)
              .children('p')
              .text(message);
          }
        }
      });
    });

    // Index settings area close button
    $(document).on('click', '.postbox__close', (event) => {
      const index = $(event.currentTarget).data('index-form');
      const $getSettingsButton = $(
        `button.get-index-settings[data-index=${index}]`
      );

      $(`tr.index-settings[data-index=${index}]`).fadeOut(150, () => {
        $getSettingsButton.prop('disabled', false);
      });
    });

    // Toggle all indices checkbox
    $('#toggle-all-indices').on('click', (event) => {
      const $this = $(event.currentTarget);
      $('tr.not-current-env').toggle($this.prop('checked'));
    });

    // Hook trigger button in 'Custom Hooks' tab
    $('button.hook-trigger[data-hook]').on('click', (event) => {
      const $this = $(event.currentTarget);
      const hook = $this.data('hook');
      const $thisTableRow = $this.parents('tr.custom-hooks__tr');
      const $loader = $(`.spinner[data-hook=${hook}]`);

      $this.addClass('is-hidden');
      $thisTableRow.addClass('is-loading');
      $loader.addClass('is-active');

      // Trigger hook over ajax
      $.ajax({
        url: wlAlgoliaAdmin.ajax_url,
        type: 'post',
        data: {
          action: 'trigger_custom_hook',
          hook: hook,
          inject: $this.data('inject-algolia')
        },
        dataType: 'json',
        success: ({ success, message, function_return }) => {
          const noticeMessage = `<strong>${message}</strong> ${
            success ? `<br>Function returned: <em>${function_return}</em>` : ''
          }`;

          displayNotice(success, noticeMessage);

          $this.removeClass('is-hidden');
          $thisTableRow.removeClass('is-loading');
          $loader.removeClass('is-active');
        }
      });
    });

    // Handle Post Types checkbox change on Searchable Posts Tab
    $('input.searchable-posts__post-type__toggle').on('click', () => {
      searchablePostTypes = handleSearchablePostTypesCheckboxClick();
    });

    // Save Searchable Post Types selection
    $('.save-searchable-posts-types').on('click', () => {
      $.ajax({
        url: wlAlgoliaAdmin.ajax_url,
        type: 'post',
        data: {
          action: 'save_searchable_post_types',
          postTypes: searchablePostTypes
        },
        dataType: 'json',
        success: ({ success, message }) => {
          $('.index-searchable-posts').prop('disabled', false);
          displayNotice(success, message);
        }
      });
    });

    // Trigger indexing of Searchable Posts
    $('.index-searchable-posts').on('click', (event) => {
      const $this = $(event.currentTarget);
      const $loader = $this.prev('.spinner');

      $this.hide();
      $loader.addClass('is-active');

      $.ajax({
        url: wlAlgoliaAdmin.ajax_url,
        type: 'post',
        data: {
          action: 'index_searchable_posts',
          createSettings: $this.attr('data-create-settings')
        },
        dataType: 'json',
        success: ({ success, message }) => {
          $loader.removeClass('is-active');
          $this.show();

          displayNotice(success, message);
        }
      }).always(() => {
        $this.show();
        $loader.removeClass('is-active');
      });
    });

    function handleSearchablePostTypesCheckboxClick() {
      let postTypes = [];
      let count = 0;
      const $loader = $('.searchable-posts__post-types__count .amount-spinner');

      $loader.addClass('is-active');

      // Find and store all checked post types, and keep count
      $('input.searchable-posts__post-type__toggle').each((i, element) => {
        const $this = $(element);
        if ($this.prop('checked')) {
          postTypes.push($this.val());
          count += Number($this.data('post-count'));
        }
      });

      // Disable re-index sesrchable posts btn
      $('.index-searchable-posts').prop('disabled', true);

      const post = count === 1 ? 'Post' : 'Posts';

      setTimeout(() => {
        $('.searchable-posts__post-types__amount').text(`${count} ${post}`);
        $loader.removeClass('is-active');
      }, 200);

      return postTypes;
    }

    function displayNotice(success, message) {
      $notice
        .attr('class', '')
        .addClass(
          `notice ${success ? 'notice-success' : 'notice-error'} inline`
        )
        .show()
        .children('p')
        .html(message);
    }
  });

  function createSettingsForm(formData, indexName, hasReplicas) {
    const {
      attributesToIndex,
      searchableAttributes,
      ranking,
      customRanking,
      primary,
      attributesForFaceting
    } = formData;

    let formHTML = '<div class="postbox">';
    formHTML += `<span class="postbox__close dashicons dashicons-no-alt" data-index-form="${indexName}"></span>`;
    formHTML += `<h3>Showing most relevant settings for <em>${indexName}</em></h3>`;
    formHTML += '<hr />';
    formHTML +=
      '<p class="description">Note that the order of the items shown in these fields holds <strong>significance</strong>.</p>';
    formHTML += `<form class="index-settings-form" data-index-form="${indexName}">`;

    if (hasReplicas) {
      formHTML += `<p>This index appears to have replicas. <label style="font-weight:bold;">Forward Settings to Replicas? <input type="checkbox" name="forwardToReplicas" /></label></p>`;
    }

    formHTML += '<table class="form-table"><tbody><tr>';

    if (attributesToIndex) {
      formHTML += createFormTableRow(
        'Attributes To Index',
        attributesToIndex.join(', ')
      );
    }

    if (searchableAttributes) {
      formHTML += createFormTableRow(
        'Searchable Attributes',
        searchableAttributes.join(', ')
      );
    }

    // Always show Custom Ranking
    formHTML += createFormTableRow(
      'Custom Ranking',
      customRanking ? customRanking.join(', ') : '',
      primary
        ? ''
        : 'Note: this is only really appropriate to use for replica indices.'
    );

    if (ranking) {
      formHTML += createFormTableRow('Ranking', ranking.join(', '));
    }

    if (attributesForFaceting) {
      formHTML += createFormTableRow(
        'Attributes For Faceting',
        attributesForFaceting.join(', ')
      );
    }

    formHTML += '</tbody></table>';
    formHTML += `<button type="submit" class="button button-primary push-settings" data-index-form="${indexName}" disabled>Push Setting Changes to Algolia</button>`;
    formHTML += '</form>';
    formHTML += `<div class="notice is-dismissable" data-index-form="${indexName}"><p></p></div>`;
    formHTML += `<div class="spinner" data-index-form="${indexName}"></div></div>`;
    return formHTML;

    function createFormTableRow(label, values, description) {
      const inputName = `${(
        label.charAt(0).toLowerCase() + label.slice(1)
      ).replace(/ /g, '')}`;

      let formRowHTML = `
      <tr>
        <th scope="row">
          <label>${label}</label>
        </th>
        <td>
          <input
            type="text"
            name="${inputName}"
            class="large-text code"
            data-index-form="${indexName}"
            value="${values}"
          />`;

      if (description) {
        formRowHTML += `<span class="description">${description}</span>`;
      }

      formRowHTML += `
        </td>
      </tr>`;

      return formRowHTML;
    }
  }

  function getSearchablePostTypes() {
    $.ajax({
      url: wlAlgoliaAdmin.ajax_url,
      type: 'post',
      data: {
        action: 'get_searchable_post_types'
      },
      dataType: 'json',
      success: ({ data }) => {
        return data;
      }
    });

    return [];
  }
})(jQuery);
