/**
 * @file
 * The light/dark toggle: cycles automatic (the system setting), light and dark, and remembers the choice in this
 * browser. The inline script in templates/layout/html.html.twig applies a saved choice before the page paints.
 */
((Drupal, once) => {
  const KEY = 'marlene-color-scheme';
  const ORDER = ['auto', 'light', 'dark'];
  const LABELS = {
    auto: Drupal.t('Color scheme: automatic. Switch to light.'),
    light: Drupal.t('Color scheme: light. Switch to dark.'),
    dark: Drupal.t('Color scheme: dark. Switch to automatic.'),
  };

  const read = () => {
    try {
      const value = localStorage.getItem(KEY);
      return ORDER.includes(value) ? value : 'auto';
    }
    catch (e) {
      return 'auto';
    }
  };

  const apply = (scheme, button) => {
    const root = document.documentElement;
    if (scheme === 'auto') {
      root.removeAttribute('data-color-scheme');
    }
    else {
      root.setAttribute('data-color-scheme', scheme);
    }
    button.dataset.scheme = scheme;
    button.querySelector('[data-color-scheme-label]').textContent = LABELS[scheme];
  };

  Drupal.behaviors.marleneColorScheme = {
    attach(context) {
      once('marlene-color-scheme', '[data-color-scheme-toggle]', context).forEach((button) => {
        apply(read(), button);
        button.hidden = false;
        button.addEventListener('click', () => {
          const next = ORDER[(ORDER.indexOf(button.dataset.scheme) + 1) % ORDER.length];
          try {
            if (next === 'auto') {
              localStorage.removeItem(KEY);
            }
            else {
              localStorage.setItem(KEY, next);
            }
          }
          catch (e) {
            // Storage blocked: the choice lasts for this page only.
          }
          apply(next, button);
        });
      });
    },
  };
})(Drupal, once);
