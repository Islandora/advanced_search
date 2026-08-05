//# sourceURL=modules/contrib/islandora/modules/advanced_search/js/facets/facets-view.ajax.js
/**
 * @file
 * Coordinates Advanced Search controls with their owning AJAX View.
 */
(function ($, Drupal, drupalSettings, once) {
  'use strict';

  const toolbarSelector = '[data-drupal-pager-id]';
  const historyStateKey = 'advancedSearchToolbarId';

  /**
   * Finds a toolbar without interpolating an identifier into a CSS selector.
   */
  function findToolbar(toolbarId) {
    if (!toolbarId) {
      return null;
    }
    return (
      Array.from(document.querySelectorAll(toolbarSelector)).find(
        (toolbar) =>
          toolbar.getAttribute('data-drupal-pager-id') === toolbarId,
      ) || null
    );
  }

  /**
   * Returns the server-emitted ownership settings for a toolbar.
   */
  function getToolbarSettings(toolbar) {
    if (!toolbar) {
      return null;
    }
    const toolbarId = toolbar.getAttribute('data-drupal-pager-id');
    const emitted =
      drupalSettings.advanced_search_pager_views_ajax?.[toolbarId];
    if (emitted) {
      return emitted;
    }

    // Data attributes keep normal links usable even if settings are absent.
    return {
      view_id: toolbar.dataset.advancedSearchViewId,
      current_display_id: toolbar.dataset.advancedSearchDisplayId,
      view_dom_id: toolbar.dataset.advancedSearchViewDomId,
      ajax_enabled: toolbar.dataset.advancedSearchAjaxEnabled === 'true',
    };
  }

  /**
   * Reads the View DOM identifier from the toolbar's closest View wrapper.
   */
  function getClosestViewDomId(toolbar) {
    const view = toolbar?.closest('.view');
    if (!view) {
      return null;
    }
    const domClass = Array.from(view.classList).find((className) =>
      className.startsWith('js-view-dom-id-'),
    );
    return domClass ? domClass.slice('js-view-dom-id-'.length) : null;
  }

  /**
   * Finds the one core AJAX View instance owned by a toolbar.
   */
  function getViewInstance(toolbar) {
    const toolbarSettings = getToolbarSettings(toolbar);
    if (!toolbarSettings?.ajax_enabled || !Drupal.views?.instances) {
      return null;
    }

    const possibleDomIds = [
      getClosestViewDomId(toolbar),
      toolbarSettings.view_dom_id,
    ].filter(Boolean);
    for (const domId of possibleDomIds) {
      const instance = Drupal.views.instances[`views_dom_id:${domId}`];
      if (instance) {
        return instance;
      }
    }

    // A legacy toolbar block executes its own copy of the View, so its emitted
    // DOM id differs from the View rendered elsewhere on the page. Fall back
    // to the stable View/display identity only when it is unambiguous.
    const matches = Object.values(Drupal.views.instances).filter(
      (instance) =>
        instance.settings?.view_name === toolbarSettings.view_id &&
        instance.settings?.view_display_id ===
          toolbarSettings.current_display_id,
    );
    return matches.length === 1 ? matches[0] : null;
  }

  /**
   * Finds the toolbar owned by one core AJAX View setting.
   */
  function findToolbarForView(viewSettings) {
    const exactView = Array.from(
      document.getElementsByClassName(
        `js-view-dom-id-${viewSettings.view_dom_id}`,
      ),
    )[0];
    const exactToolbar = exactView?.querySelector(toolbarSelector);
    if (exactToolbar) {
      return exactToolbar;
    }

    const matches = Array.from(document.querySelectorAll(toolbarSelector)).filter(
      (toolbar) => {
        const toolbarSettings = getToolbarSettings(toolbar);
        return (
          toolbarSettings?.view_id === viewSettings.view_name &&
          toolbarSettings?.current_display_id === viewSettings.view_display_id
        );
      },
    );
    return matches.length === 1 ? matches[0] : null;
  }

  /**
   * Parses query parameters while retaining repeated values.
   */
  function parseQueryParameters(url) {
    const params = {};
    new URL(url, window.location.origin).searchParams.forEach((value, key) => {
      if (Object.prototype.hasOwnProperty.call(params, key)) {
        params[key] = Array.isArray(params[key])
          ? params[key].concat(value)
          : [params[key], value];
      }
      else {
        params[key] = value;
      }
    });
    return params;
  }

  /**
   * Keeps one toolbar's links, state, and owning View presentation in sync.
   */
  function syncToolbar(toolbar, url) {
    const destination = new URL(url, window.location.origin);
    const updateLinks = (selector, parameter, attribute) => {
      toolbar.querySelectorAll(selector).forEach((link) => {
        const linkUrl = new URL(destination.toString());
        linkUrl.searchParams.set(parameter, link.getAttribute(attribute));
        link.href = linkUrl.toString();
      });
    };
    updateLinks(
      'a.pager__itemsperpage',
      'items_per_page',
      'itemsperpage',
    );
    updateLinks('a.pager__display', 'display', 'type');

    const display = destination.searchParams.get('display');
    if (display === 'list' || display === 'grid') {
      toolbar.querySelectorAll('a.pager__display').forEach((link) => {
        const active = link.getAttribute('type') === display;
        const label = link.querySelector('.display-mode')?.textContent.trim();
        link.parentElement?.classList.toggle('is-active', active);
        link.classList.toggle('pager__link--is-active', active);
        if (active) {
          link.setAttribute('aria-current', 'true');
        }
        else {
          link.removeAttribute('aria-current');
        }
        if (label) {
          link.setAttribute(
            'aria-label',
            active
              ? Drupal.t('Current display: @display', { '@display': label })
              : Drupal.t('Display as @display', { '@display': label }),
          );
        }
      });

      const instance = getViewInstance(toolbar);
      const view = toolbar.closest('.view') || instance?.$view?.get(0);
      view?.classList.toggle('view-list', display === 'list');
      view?.classList.toggle('view-grid', display === 'grid');
    }

    const itemsPerPage = destination.searchParams.get('items_per_page');
    if (itemsPerPage !== null) {
      toolbar.querySelectorAll('a.pager__itemsperpage').forEach((link) => {
        const active = link.getAttribute('itemsperpage') === itemsPerPage;
        const label = link.textContent.trim();
        link.parentElement?.classList.toggle('is-active', active);
        link.classList.toggle('pager__link--is-active', active);
        if (active) {
          link.setAttribute('aria-current', 'true');
        }
        else {
          link.removeAttribute('aria-current');
        }
        link.setAttribute(
          'aria-label',
          active
            ? Drupal.t('Current page size: @item items per page', {
                '@item': label,
              })
            : Drupal.t('@item items per page', { '@item': label }),
        );
      });
    }
  }

  /**
   * Collects only legacy blocks associated with the toolbar's View display.
   */
  function getRelatedBlocks(toolbar, instance) {
    const blocks = {};
    const owningView = instance.$view?.get(0);
    const addBlock = (element) => {
      const block = element?.closest?.('[id^="block-"]');
      if (!block || (owningView && block.contains(owningView))) {
        return;
      }
      const htmlId = block.id.replace(/--.*$/, '');
      const blockId = htmlId.slice('block-'.length).replace(/-/g, '_');
      if (blockId) {
        blocks[blockId] = `#${block.id}`;
      }
    };

    addBlock(toolbar);
    addBlock(instance.$exposed_form?.get(0));

    const toolbarSettings = getToolbarSettings(toolbar);
    Object.entries(drupalSettings.facets_views_ajax || {}).forEach(
      ([facetId, facetSettings]) => {
        if (
          facetSettings.view_id !== toolbarSettings.view_id ||
          facetSettings.current_display_id !==
            toolbarSettings.current_display_id
        ) {
          return;
        }
        document
          .querySelectorAll('[data-drupal-facet-id]')
          .forEach((element) => {
            if (element.getAttribute('data-drupal-facet-id') === facetId) {
              addBlock(element);
            }
          });
        if (facetSettings.facets_summary_id) {
          document
            .querySelectorAll('[data-drupal-facets-summary-id]')
            .forEach((element) => {
              if (
                element.getAttribute('data-drupal-facets-summary-id') ===
                facetSettings.facets_summary_id
              ) {
                addBlock(element);
              }
            });
        }
      },
    );
    return blocks;
  }

  /**
   * Reloads only the AJAX View and legacy blocks owned by one toolbar.
   */
  function reloadToolbar(toolbar, url) {
    const instance = getViewInstance(toolbar);
    if (!instance) {
      return false;
    }

    syncToolbar(toolbar, url);
    const toolbarSettings = getToolbarSettings(toolbar);
    const destination = new URL(url, window.location.origin);
    const viewSettings = instance.settings;
    const viewArguments = Drupal.Views.parseViewArgs(
      destination.toString(),
      viewSettings.view_base_path,
    );
    const submit = $.extend(
      {},
      viewSettings,
      viewArguments,
      parseQueryParameters(destination.toString()),
    );
    let ajaxPath =
      toolbarSettings.ajax_path || drupalSettings.views?.ajax_path || '';
    if (Array.isArray(ajaxPath)) {
      [ajaxPath] = ajaxPath;
    }
    const separator = ajaxPath.includes('?') ? '&' : '?';
    const ajaxSettings = $.extend({}, instance.element_settings, {
      submit,
      url: `${ajaxPath}${separator}${destination.searchParams.toString()}`,
      httpMethod: 'GET',
    });
    Drupal.ajax(ajaxSettings).execute();

    const blocks = getRelatedBlocks(toolbar, instance);
    if (Object.keys(blocks).length > 0) {
      Drupal.ajax({
        url: Drupal.url('islandora-advanced-search-ajax-blocks'),
        submit: {
          link: destination.toString(),
          blocks,
        },
      }).execute();
    }
    return true;
  }

  /**
   * Uses AJAX for one toolbar, retaining ordinary navigation as the fallback.
   */
  function navigate(toolbar, url) {
    if (!getViewInstance(toolbar)) {
      return false;
    }
    const toolbarId = toolbar.getAttribute('data-drupal-pager-id');
    const currentState =
      window.history.state && typeof window.history.state === 'object'
        ? window.history.state
        : {};
    if (currentState[historyStateKey] !== toolbarId) {
      window.history.replaceState(
        { ...currentState, [historyStateKey]: toolbarId },
        document.title,
        window.location.href,
      );
    }
    window.history.pushState(
      { [historyStateKey]: toolbarId },
      document.title,
      url,
    );
    reloadToolbar(toolbar, url);
    window.dispatchEvent(
      new CustomEvent('advancedsearchlocationchange', {
        detail: { toolbarId, url: window.location.href },
      }),
    );
    return true;
  }

  Drupal.advancedSearchNavigate = (toolbarId, url) => {
    const toolbar = findToolbar(toolbarId);
    return toolbar ? navigate(toolbar, url) : false;
  };

  function handlePopState(event) {
    const toolbarId = event.state?.[historyStateKey];
    const toolbar = findToolbar(toolbarId);
    if (toolbar && reloadToolbar(toolbar, window.location.href)) {
      window.dispatchEvent(
        new CustomEvent('advancedsearchlocationchange', {
          detail: { toolbarId, url: window.location.href },
        }),
      );
    }
  }

  window.addEventListener('popstate', handlePopState);

  Drupal.behaviors.islandoraAdvancedSearchViewsAjax = {
    attach(context, settings) {
      document.querySelectorAll(toolbarSelector).forEach((toolbar) => {
        toolbar.dataset.advancedSearchAjaxReady = getViewInstance(toolbar)
          ? 'true'
          : 'false';
      });

      once('advanced-search-toolbar', toolbarSelector, context).forEach(
        (toolbar) => {
          syncToolbar(toolbar, window.location.href);
          $(toolbar).on(
            'click',
            'a.pager__itemsperpage, a.pager__display, nav.pager a',
            function (event) {
              if (
                event.shiftKey ||
                event.ctrlKey ||
                event.metaKey ||
                event.altKey ||
                (typeof event.button === 'number' && event.button !== 0) ||
                !getViewInstance(toolbar)
              ) {
                return;
              }
              event.preventDefault();
              event.stopImmediatePropagation();
              navigate(toolbar, this.href);
            },
          );

          $(toolbar)
            .find('select[name="order"]')
            .on('change', function () {
              const href = new URL(window.location.href);
              const selection = this.value;
              const separator = selection.lastIndexOf('_');
              if (separator === -1) {
                return;
              }
              href.searchParams.set(
                'sort_by',
                selection.slice(0, separator),
              );
              href.searchParams.set(
                'sort_order',
                selection.slice(separator + 1).toUpperCase(),
              );
              href.searchParams.delete('page');
              if (!navigate(toolbar, href.toString())) {
                window.location.assign(href.toString());
              }
            });
        },
      );

      Object.values(settings.views?.ajaxViews || {}).forEach((viewSettings) => {
        const toolbar = findToolbarForView(viewSettings);
        const instance = toolbar ? getViewInstance(toolbar) : null;
        const form = instance?.$exposed_form?.get(0);
        if (!toolbar || !form) {
          return;
        }

        const submitForm = (event) => {
          if (event.submitter?.matches('[data-drupal-selector="edit-reset"]')) {
            return;
          }
          event.preventDefault();
          event.stopImmediatePropagation();
          const href = new URL(window.location.href);
          href.searchParams.delete('page');
          $(form)
            .find(':input[name]')
            .each(function () {
              href.searchParams.delete(this.name);
            });
          $(form)
            .serializeArray()
            .forEach(({ name, value }) => {
              href.searchParams.append(name, value);
            });
          navigate(toolbar, href.toString());
        };

        once('advanced-search-exposed-form', form).forEach((element) => {
          element.addEventListener('submit', submitForm, true);
        });
        once(
          'advanced-search-exposed-submit',
          $(form)
            .find(
              'input[type="submit"], button[type="submit"], input[type="image"]',
            )
            .not('[data-drupal-selector="edit-reset"]')
            .get(),
        ).forEach((element) => {
          element.addEventListener('click', submitForm, true);
        });
      });

      // Facets 2 emits this ownership map. Facets 3 uses Views exposed filters,
      // so its native controls are deliberately left untouched.
      Object.entries(settings.facets_views_ajax || {}).forEach(
        ([facetId, facetSettings]) => {
          const toolbar = Array.from(
            document.querySelectorAll(toolbarSelector),
          ).find((candidate) => {
            const toolbarSettings = getToolbarSettings(candidate);
            return (
              toolbarSettings?.view_id === facetSettings.view_id &&
              toolbarSettings?.current_display_id ===
                facetSettings.current_display_id &&
              getViewInstance(candidate)
            );
          });
          if (!toolbar) {
            return;
          }

          if (facetId === 'facets_summary_ajax') {
            once(
              'advanced-search-facets-summary',
              '[data-drupal-facets-summary-id] a',
              context,
            ).forEach((link) => {
              link.addEventListener('click', (event) => {
                if (
                  event.shiftKey ||
                  event.ctrlKey ||
                  event.metaKey ||
                  event.altKey
                ) {
                  return;
                }
                event.preventDefault();
                event.stopImmediatePropagation();
                navigate(toolbar, link.href);
              });
            });
            return;
          }

          $('[data-drupal-facet-id]', context)
            .filter(function () {
              return this.getAttribute('data-drupal-facet-id') === facetId;
            })
            .each(function () {
              if (!this.classList.contains('js-facets-widget')) {
                return;
              }
              $(this)
                .off('facets_filter.facets')
                .on('facets_filter.facets', (_event, url) => {
                  navigate(toolbar, url);
                });
            });
        },
      );
    },
  };
})(jQuery, Drupal, drupalSettings, once);
