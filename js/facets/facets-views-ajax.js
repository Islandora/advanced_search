//# sourceURL=modules/contrib/islandora/modules/advanced_search/js/facets/facets-view.ajax.js
/**
 * @file
 * Overrides the facets-view-ajax.js behavior from the 'facets' module.
 */
(function ($, Drupal) {
  "use strict";

  // For each search view override display mode config (in SearchPagerResultBlock.php)
  jQuery( document ).ready(function() {
    handleDisplayMode(); 
  });

  jQuery(document).ajaxComplete(function() {
    handleDisplayMode();
  });

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

  function handleDisplayMode() {
      var initial_display = jQuery('a.pager__link.pager__link--is-active.pager__display').attr('type');
      if (initial_display == undefined) {
        initial_display = jQuery("#override-default-display-mode").html();
      }
      if (jQuery('div.view.view-list').length == 1 && initial_display != "list") {
        var search_view = jQuery('div.view.view-list');
        search_view.removeClass("view-list");
        search_view.addClass("view-grid");
      }
      if (jQuery('div.view.view-grid').length == 1 && initial_display != "grid") {
        var search_view = jQuery('div.view.view-grid');
        search_view.removeClass("view-grid");
        search_view.addClass("view-list");
      }
  }
  
  /**
   * Parse query parameters without discarding repeated exposed-filter values.
   */
  function parseQueryParameters(url) {
    var params = {};
    new URL(url, window.location.origin).searchParams.forEach(function (
      value,
      key
    ) {
      if (Object.prototype.hasOwnProperty.call(params, key)) {
        params[key] = Array.isArray(params[key])
          ? params[key].concat(value)
          : [params[key], value];
      } else {
        params[key] = value;
      }
    });
    return params;
  }

  /**
   * Update one pager parameter while preserving every active filter value.
   */
  function updatePagerLinks(url, selector, parameter, attribute) {
    $(selector).each(function () {
      var newUrl = new URL(url, window.location.origin);
      newUrl.searchParams.set(parameter, $(this).attr(attribute));
      $(this).attr("href", newUrl.toString());
    });
  }

  function reload(url) {
    // Update View.
    if (drupalSettings && drupalSettings.views && drupalSettings.views.ajaxViews) {
      var view_path = drupalSettings.views.ajax_path;
      $.each(drupalSettings.views.ajaxViews, function (views_dom_id) {
        var views_parameters = parseQueryParameters(url);
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
          view_path +
          "?" +
          new URL(url, window.location.origin).searchParams.toString();
        Drupal.ajax(views_ajax_settings).execute();
      });
    }

    // Update pager links without flattening multi-value facet parameters.
    updatePagerLinks(
      url,
      "a.pager__itemsperpage",
      "items_per_page",
      "itemsperpage"
    );
    updatePagerLinks(url, "a.pager__display", "display", "type");


    
    // Replace filter, pager, summary, and facet blocks.
    var blocks = {};
    $(
      "[class*='block-plugin-id--islandora-advanced-search-result-pager'], [class*='block-plugin-id--views-exposed-filter-block'], [class*='block-facets']"
    ).each(function () {
      var id = $(this).attr("id");
      var block_id = id
        .slice("block-".length, id.length)
        .replace(/--.*$/g, "")
        .replace(/-/g, "_");
      blocks[block_id] = "#" + id;
    });
    if (Object.keys(blocks).length > 0) {
      Drupal.ajax({
        url: Drupal.url("islandora-advanced-search-ajax-blocks"),
        submit: {
          link: url,
          blocks: blocks,
        },
      }).execute();
    }
  }

  // On location change reload all the blocks / ajax view.
  window.addEventListener("pushstate", function (e) {
    reload(window.location.href);
  });

  window.addEventListener("popstate", function (e) {
      reload(window.location.href);
  });

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
          $(once('exposed-form',
            "form#views-exposed-form-" +
            settings.view_name.replace(/_/g, "-") +
            "-" +
            settings.view_display_id.replace(/_/g, "-")))
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
                var href = new URL(window.location.href);
                // Remove the page if set as submitting the form should always take
                // the user to the first page (facets do the same).
                href.searchParams.delete("page");
                // Remove stale values, including unchecked facet options.
                exposed_form.find(":input[name]").each(function () {
                  href.searchParams.delete(this.name);
                });
                // Include every selected value from the form in the URL.
                $.each(exposed_form.serializeArray(), function () {
                  href.searchParams.append(this.name, this.value);
                });
                window.history.pushState(null, document.title, href.toString());
              });
            });
        });

          if (window.location.search.includes("display=") === true) {

              $("li.pager__item a.pager__display").each(function () {
                  $(this).parent().removeClass("is-active");
                  $(this).removeClass("pager__link--is-active");
                  if ($(this).attr('type').trim().toLowerCase() === getParam(window.location.search, "display").trim().toLowerCase()) {
                      $(this).addClass("pager__link--is-active");
                  }
              });
          }

          if (window.location.search.includes("items_per_page=") === true) {
              $("li.pager__item a.pager__itemsperpage").each(function() {
                  $(this).parent().removeClass("is-active");
                  $(this).removeClass("pager__link--is-active");
                  if ($(this).text().trim().toLowerCase() === getParam(window.location.search, "items_per_page").trim().toLowerCase()) {
                      $(this).addClass("pager__link--is-active");
                  }
              });
          }


      }
        function getParam(urlstring, param) {
            var searchparam = new URLSearchParams(urlstring);
            return searchparam.get(param);
        }

      // Attach behavior to pager, summary, facet links.
      $(once("new-window", "[data-drupal-pager-id], [data-drupal-facets-summary-id], [data-drupal-facet-id]"))
        .find("a:not(.facet-item)")
        .click(function (e) {
          // Let ctrl/cmd click open in a new window.
          if (e.shiftKey || e.ctrlKey || e.metaKey) {
            return;
          }
          e.preventDefault();

          // added to prevent page reload if a facet link is clicked (Ajax of view is enabled)
          e.stopImmediatePropagation();

          window.history.pushState(null, document.title, $(this).attr("href"));
        });

      // Trigger on sort change.
      $(once('params-sort', '[data-drupal-pager-id] select[name="order"], .pager__sort select[name="order"]'))
        .change(function () {
          var href = new URL(window.location.href);

          var selection = $(this).val();
          var option = selection.split('_');
          href.searchParams.set("sort_order", option[option.length - 1].toUpperCase());
          href.searchParams.set("sort_by", selection.replace("_" + option[option.length - 1], ""));
          window.history.pushState(null, document.title, href.toString());
        });

    },
  };
})(jQuery, Drupal);
