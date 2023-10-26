//# sourceURL=modules/contrib/islandora/modules/advanced_search/js/facets/facets-view.ajax.js
/**
 * @file
 * Overrides the facets-view-ajax.js behavior from the 'facets' module.
 */
(function ($, Drupal) {
  "use strict";

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

  function parseQueryString( queryString ) {
        var params = {}, queries, temp, i, l;

        // Split into key/value pairs
        queries = queryString.split("&");

        // Convert the array of strings into an object
        for ( i = 0, l = queries.length; i < l; i++ ) {
            temp = queries[i].split('=');
            params[temp[0]] = temp[1];
        }

        return params;
    };

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

    // Update items_per_page links in pager 
    if (url.indexOf("items_per_page=") == -1) { 
      // append items_per_page
      $("a.pager__itemsperpage").each(function( index ) {
        var newUrl = url + "&items_per_page=" + $(this).html();
        $(this).attr("href", newUrl);
      });
    } 
    else { 
      // replace existed items_per_page
      var params = parseQueryString(url.split("?")[1]);
      var newParams = [];
      var existingDateQuery = false; // true if a date query already exists

      var links = {};
      // update publication date in url if previously queried
      for (var key in params) {
        if (!params[key]) { // no search parameters in url
          break;
        }

        // check for items_per_page query
        if (!key.startsWith("items_per_page")) { 
          newParams.push(key + "=" + params[key]);
        }
      }
      var newParamsUrl = newParams.join('&');
      $("a.pager__itemsperpage").each(function( index ) {
        $(this).attr("href", url.split("?")[0] + '?' + newParamsUrl + "&items_per_page=" + $(this).html());
      });
    }



    // Update display mode links in pager 
    if (url.indexOf("display=") == -1) { 
      // append items_per_page
      $("a.pager__display").each(function( index ) {
        var newUrl = url + "&display=" + $(this).find(".display-mode").html().toLowerCase();
        $(this).attr("href", newUrl);
      });
    } 
    else { 
      // replace existed display
      var params = parseQueryString(url.split("?")[1]);
      var newParams = [];
      var existingDateQuery = false; // true if a date query already exists

      var links = {};
      // update publication date in url if previously queried
      for (var key in params) {
        if (!params[key]) { // no search parameters in url
          break;
        }

        // check for display query
        if (!key.startsWith("display")) { 
          newParams.push(key + "=" + params[key]);
        }
      }
      var newParamsUrl = newParams.join('&');
      $("a.pager__display").each(function( index ) {

        var value = $(this).find(".display-mode").html().toLowerCase();
        $(this).attr("href", url.split("?")[0] + '?' + newParamsUrl + "&display=" + value);
      });
    }


    
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
    reload(window.location.href);
  });

  window.addEventListener("popstate", function (e) {
    if (e.state != null) {
      reload(window.location.href);
    }
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

          if (window.location.search.includes("display=") === true) {

              $("li.pager__item a.pager__display").each(function () {
                  $(this).parent().removeClass("is-active");
                  $(this).removeClass("pager__link--is-active");
                  if ($(this).text().trim().toLowerCase() === getParam(window.location.search, "display").trim().toLowerCase()) {
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
          var href = window.location.href;
          var params = Drupal.Views.parseQueryString(href);

          var selection = $(this).val();
          var option = selection.split('_');
          params.sort_order = option[option.length - 1].toUpperCase();
          params.sort_by = selection.replace("_" + option[option.length - 1], "");
          href = href.split("?")[0] + "?" + $.param(params);
          window.history.pushState(null, document.title, href);
        });

    },
  };
})(jQuery, Drupal);
