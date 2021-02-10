//# sourceURL=modules/contrib/islandora/modules/islandora_advanced_search/js/facets/soft-limit.js
/**
 * @file
 * Overrides the soft-limit.js behavior from the 'facets' module.
 * As when having many facets the original version causes the page to slow down and snap to hidden when rendering.
 */
(function ($) {

  'use strict';

  Drupal.behaviors.facetSoftLimit = {
    attach: function (context, settings) {
      if (settings.facets.softLimit !== 'undefined') {
        $.each(settings.facets.softLimit, function (facet, limit) {
          Drupal.facets.applySoftLimit(facet, limit, settings);
        });
      }
    }
  };

  Drupal.facets = Drupal.facets || {};

  /**
   * Applies the soft limit UI feature to a specific facets list.
   *
   * @param {string} facet
   *   The facet id.
   * @param {string} limit
   *   The maximum amount of items to show.
   * @param {object} settings
   *   Settings.
   */
  Drupal.facets.applySoftLimit = function (facet, limit, settings) {
    var zero_based_limit = (limit - 1);
    var facet_id = facet;
    var facetsList = $('ul[data-drupal-facet-id="' + facet_id + '"]');

    // In case of multiple instances of a facet, we need to key them.
    if (facetsList.length > 1) {
      facetsList.each(function (key, $value) {
        $(this).attr('data-drupal-facet-id', facet_id + '-' + key);
      });
    }

    // Add "Show more" / "Show less" links.
    facetsList.filter(function () {
      return $(this).next('ul').length == 1; // Has expanding list.
    }).each(function () {
      var facet = $(this);
      var expand = facet.next('ul');
      var link = expand.next('a');
      var showLessLabel = settings.facets.softLimitSettings[facet_id].showLessLabel;
      var showMoreLabel = settings.facets.softLimitSettings[facet_id].showMoreLabel;
      link.text(showMoreLabel)
        .once()
        .on('click', function () {
          if (!expand.is(":visible")) {
            expand.slideDown();
            $(this).addClass('open').text(showLessLabel);
          }
          else {
            expand.slideUp();
            $(this).removeClass('open').text(showMoreLabel);
          }
          return false;
        })
    });
  };

})(jQuery);
