//# sourceURL=modules/contrib/islandora/modules/islandora_advanced_search/js/facets/facets-view.ajax.js
/**
 * @file
 * Overrides the facets-view-ajax.js behavior from the 'facets' module.
 */
(function ($, Drupal) {
  "use strict";
  showByDisplayMode(null);
  // Generate events on push state.
  (function (history) {
    var pushState = history.pushState;
    history.pushState = function (state, title, url) {
      var ret = pushState.apply(this, arguments);
      var event = new Event("pushstate");
      window.dispatchEvent(event);
      return ret;
    };
  })(window.history);

  function reload(url) {
    // Update View.
    if (drupalSettings && drupalSettings.views && drupalSettings.views.ajaxViews) {
      var view_path = drupalSettings.views.ajax_path;
      $.each(drupalSettings.views.ajaxViews, function (views_dom_id) {
        var views_parameters = Drupal.Views.parseQueryString(url);
        var views_arguments = Drupal.Views.parseViewArgs(url, "search");
        var views_settings = $.extend(
          {},
          Drupal.views.instances[views_dom_id].settings,
          views_arguments,
          views_parameters
        );
        var views_ajax_settings =
          Drupal.views.instances[views_dom_id].element_settings;
        views_ajax_settings.submit = views_settings;
        views_ajax_settings.url =
          view_path + "?" + $.param(Drupal.Views.parseQueryString(url));
        Drupal.ajax(views_ajax_settings).execute();
      });
    }

    // Replace filter, pager, summary, and facet blocks.
    var blocks = {};
    $(
      ".block[class*='block-plugin-id--islandora-advanced-search-result-pager'], .block[class*='block-plugin-id--views-exposed-filter-block'], .block[class*='block-facets']"
    ).each(function () {
      var id = $(this).attr("id");
      var block_id = id
        .slice("block-".length, id.length)
        .replace(/--.*$/g, "")
        .replace(/-/g, "_");
      blocks[block_id] = "#" + id;
    });
    Drupal.ajax({
      url: Drupal.url("islandora-advanced-search-ajax-blocks"),
      submit: {
        link: url,
        blocks: blocks,
      },
    }).execute();
  }

  // On location change reload all the blocks / ajax view.
  window.addEventListener("pushstate", function (e) {
    console.log("pushstate....");
    updatePagerElementsStatus();
    reload(window.location.href);


  });

  window.addEventListener("popstate", function (e) {
    console.log("popstate....");
    updatePagerElementsStatus();
    if (e.state != null) {
      reload(window.location.href);
    }

  });

  window.addEventListener('beforeunload', function (e) {
    //e.preventDefault();
    //e.returnValue = '';

    //delete e['returnValue'];
  });

  function updatePagerElementsStatus () {
    var urlParams = new URLSearchParams(window.location.search);

    if (typeof urlParams.get('page') !== 'undefined') {
      // the variable is defined
      $(".pager .pager__item").removeClass("is-active");
      $(".pager .pager__item").each(function() {
        if ($(this).text() === urlParams.get('page')) {
          $(this).addClass("is-active");
        }
      });
    }
    else {
      // the variable is defined
      $(".pager .pager__item").removeClass("is-active");
      $(".pager .pager__item").each(function(index) {
        console.log(index);
        if (index === 0) {
          $(this).addClass("is-active");
        }
      });
    }

    if (typeof urlParams.get('items_per_page') !== 'undefined') {
      // the variable is defined
      $(".pager__results .pager__item").removeClass("is-active");
      $(".pager__results .pager__item").each(function() {
        if ($(this).text() === urlParams.get('items_per_page')) {
          $(this).addClass("is-active");
        }
      });
    }

    if (typeof urlParams.get('display') !== 'undefined') {
      // the variable is defined
      $(".pager__display .pager__item").removeClass("is-active");
      $(".pager__display .pager__item").each(function() {
        var displaymode = $(this).text().toLowerCase();
        if (displaymode.includes(urlParams.get('display'))) {
          $(this).addClass("is-active");
          showByDisplayMode(displaymode);

        }
      });
    }


  }

  function showByDisplayMode(displaymode = null) {
    if (displaymode === "") {
      var urlParams = new URLSearchParams(window.location.search);
      var displaymode = $(this).text().toLowerCase();
    }

    $(document).ready(function (event) {
      if (displaymode !== null && displaymode.includes("grid")) {
        console.log("show as grid");
        $('.views-view-grid .item').removeClass('list-group-item');
        $('.views-view-grid .item').addClass('grid-group-item');
      }
      else {
        console.log("show as list");
        $('.views-view-grid .item').addClass('list-group-item');
      }
    });
  }

  /**
   * Push state on form/pager/facet change.
   */
  Drupal.behaviors.islandoraAdvancedSearchViewsAjax = {
    attach: function (context, settings) {
      window.historyInitiated = true;

      // Remove existing behavior from form.
      if (settings && settings.views && settings.views.ajaxViews) {
        $.each(settings.views.ajaxViews, function (index, settings) {
          var exposed_form = $(
            "form#views-exposed-form-" +
            settings.view_name.replace(/_/g, "-") +
            "-" +
            settings.view_display_id.replace(/_/g, "-")
          );
          exposed_form
            .once()
            .find("input[type=submit], input[type=image]")
            .not("[data-drupal-selector=edit-reset]")
            .each(function (index) {
              $(this).unbind("click");
              $(this).click(function (e) {
                // Let ctrl/cmd click open in a new window.
                if (e.shiftKey || e.ctrlKey || e.metaKey) {
                  return;
                }
                e.preventDefault();
                e.stopPropagation();
                var href = window.location.href;
                var params = Drupal.Views.parseQueryString(href);
                // Remove the page if set as submitting the form should always take
                // the user to the first page (facets do the same).
                delete params.page;
                // Include values from the form in the URL.
                $.each(exposed_form.serializeArray(), function () {
                  params[this.name] = this.value;
                });
                href = href.split("?")[0] + "?" + $.param(params);
                window.history.pushState(null, document.title, href);
              });
            });
        });
      }

      // Attach behavior to pager, summary, facet links.
      $("[data-drupal-pager-id], [data-drupal-facets-summary-id], [data-drupal-facet-id]")
        .once()
        .find("a:not(.facet-item)")
        .click(function (e) {
          // Let ctrl/cmd click open in a new window.
          if (e.shiftKey || e.ctrlKey || e.metaKey) {
            return;
          }
          e.preventDefault();
          window.history.pushState(null, document.title, $(this).attr("href"));
        });

      // Trigger on sort change.
      $('[data-drupal-pager-id] select[name="order"]')
        .once()
        .change(function () {
          var href = window.location.href;
          var params = Drupal.Views.parseQueryString(href);
          var selection = $(this).val();
          var option = $('option[value="' + selection + '"]');
          params.sort_order = option.data("sort_order");
          params.sort_by = option.data("sort_by");
          href = href.split("?")[0] + "?" + $.param(params);
          window.history.pushState(null, document.title, href);
        });
    },
  };
})(jQuery, Drupal);
