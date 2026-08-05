//# sourceURL=modules/contrib/islandora/modules/advanced_search/js/islandora-advanced-search.form.js
/**
 * @file
 * Handles Ajax submission / updating form action on url change, etc.
 */
(function ($, Drupal, drupalSettings, once) {
  'use strict';

  const toolbarSelector = '[data-drupal-pager-id]';

  /**
   * Gets current parameters minus ones provided by the form.
   */
  function getParams(queryParameter, recurseParameter) {
    const params = new URLSearchParams(window.location.search);
    // Remove Advanced Search Query Parameters.
    const escapedQueryParameter = queryParameter.replace(
      /[.*+?^${}()|[\]\\]/g,
      '\\$&',
    );
    const paramRegex = new RegExp(
      `^${escapedQueryParameter}\\[\\d+\\]\\[[^\\]]+\\]$`,
    );
    Array.from(params.keys()).forEach((param) => {
      if (paramRegex.test(param)) {
        params.delete(param);
      }
    });
    // Remove Recurse parameter.
    params.delete(recurseParameter);
    // Remove the page if set as submitting the form should always take
    // the user to the first page (facets do the same).
    params.delete('page');
    return params;
  }

  /**
   * Groups form inputs by search term.
   */
  function getTerms(inputs) {
    const inputRegex = /terms\[(?<index>\d+)\]\[(?<component>.*)\]/;
    const terms = [];
    inputs.forEach(({ name, value }) => {
      const found = name.match(inputRegex);
      if (found) {
        const index = parseInt(found.groups.index, 10);
        const component = found.groups.component;
        if (typeof terms[index] !== 'object') {
          terms[index] = {};
        }
        terms[index][component] = value;
      }
    });
    return terms;
  }

  /**
   * Checks if the form user has set recursive to true in the form.
   */
  function getRecurse(inputs) {
    return inputs.some(
      ({ name, value }) => name === 'recursive' && value === '1',
    );
  }

  /**
   * Builds the destination represented by one Advanced Search form.
   */
  function buildUrl(inputs, settings) {
    const terms = getTerms(inputs);
    const recurse = getRecurse(inputs);
    const params = getParams(
      settings.query_parameter,
      settings.recurse_parameter,
    );
    for (const index in terms) {
      const term = terms[index];
      // Do not include terms with no value.
      if (String(term.value ?? '').length !== 0) {
        for (const component in term) {
          const value = term[component];
          const mappedComponent = settings.mapping[component];
          if (!mappedComponent) {
            continue;
          }
          const param = `${settings.query_parameter}[${index}][${mappedComponent}]`;
          params.set(param, value);
        }
      }
    }
    if (recurse) {
      params.set(settings.recurse_parameter, '1');
    }
    const destination = new URL(window.location.href);
    destination.search = params.toString();
    return destination.toString();
  }

  /**
   * Normalizes both current keyed settings and the legacy singleton shape.
   */
  function normalizeSettings(settings) {
    if (!settings) {
      return {};
    }
    return settings.id ? { [settings.id]: settings } : settings;
  }

  /**
   * Returns all form settings known after an initial or AJAX attachment.
   */
  function getFormSettings(settings) {
    return {
      ...normalizeSettings(drupalSettings.advanced_search_form),
      ...normalizeSettings(settings?.advanced_search_form),
    };
  }

  /**
   * Tests whether a toolbar represents the configured View display.
   */
  function toolbarMatches(toolbar, settings) {
    return (
      toolbar.dataset.advancedSearchViewId === settings.view_id &&
      toolbar.dataset.advancedSearchDisplayId === settings.display_id
    );
  }

  /**
   * Finds the form's toolbar, preferring one inside the same View instance.
   */
  function findOwnedToolbar(form, settings) {
    if (!settings.view_id || !settings.display_id) {
      return null;
    }
    const view = form.closest('.view');
    const localMatches = view
      ? Array.from(view.querySelectorAll(toolbarSelector)).filter((toolbar) =>
          toolbarMatches(toolbar, settings),
        )
      : [];
    if (localMatches.length === 1) {
      return localMatches[0];
    }

    const matches = Array.from(document.querySelectorAll(toolbarSelector)).filter(
      (toolbar) => toolbarMatches(toolbar, settings),
    );
    return matches.length === 1 ? matches[0] : null;
  }

  /**
   * Navigates through the form's AJAX-capable toolbar when available.
   */
  function navigate(form, settings, destination) {
    const toolbar = findOwnedToolbar(form, settings);
    const toolbarId = toolbar?.getAttribute('data-drupal-pager-id');
    if (
      !settings.ajax_enabled ||
      !toolbarId ||
      toolbar.dataset.advancedSearchAjaxReady !== 'true' ||
      typeof Drupal.advancedSearchNavigate !== 'function'
    ) {
      return false;
    }
    return Drupal.advancedSearchNavigate(toolbarId, destination) === true;
  }

  /**
   * Keeps live forms pointed at the browser's current search state.
   */
  function updateFormActions() {
    document
      .querySelectorAll('form[data-drupal-selector="advanced-search-form"]')
      .forEach((form) => {
        form.action = window.location.pathname + window.location.search;
      });
  }

  window.addEventListener(
    'advancedsearchlocationchange',
    updateFormActions,
  );

  Drupal.behaviors.advanced_search_form = {
    attach(context, settings) {
      Object.values(getFormSettings(settings)).forEach((formSettings) => {
        if (!formSettings?.id) {
          return;
        }
        const form = document.getElementById(formSettings.id);
        if (
          !form ||
          (context !== document && context !== form && !context.contains(form))
        ) {
          return;
        }

        once('search-form', form).forEach((element) => {
          const $form = $(element);
          element
            .querySelectorAll('input[name*="[value]"]')
            .forEach((input) => {
              input.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' || event.isComposing) {
                  return;
                }
                const submit = element.querySelector(
                  '.advanced-search-form__search',
                );
                if (!submit) {
                  return;
                }
                event.preventDefault();
                if (typeof element.requestSubmit === 'function') {
                  element.requestSubmit(submit);
                }
                else {
                  submit.click();
                }
              });
            });

          if (!formSettings.redirect) {
            element.addEventListener('submit', (event) => {
              if (
                event.submitter &&
                !event.submitter.matches('.advanced-search-form__search')
              ) {
                return;
              }
              const destination = buildUrl(
                $form.serializeArray(),
                formSettings,
              );
              if (!navigate(element, formSettings, destination)) {
                return;
              }
              event.preventDefault();
              event.stopImmediatePropagation();
            });

            const reset = element.querySelector(
              '.advanced-search-form__reset',
            );
            reset?.addEventListener('click', () => {
              $(document).one('ajaxComplete', () => {
                const destination = new URL(
                  buildUrl([], formSettings),
                );
                destination.search = '';
                if (!navigate(element, formSettings, destination.toString())) {
                  window.location.assign(destination.toString());
                }
              });
            });
          }
        });
      });
    },
  };
})(jQuery, Drupal, drupalSettings, once);
