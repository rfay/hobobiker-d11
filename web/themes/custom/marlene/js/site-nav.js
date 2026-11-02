/**
 * @file
 * The main menu: a disclosure menu (https://www.w3.org/WAI/ARIA/apg/patterns/disclosure/).
 *
 * Wide screens: a top-level button opens its panel under the header; one panel at a time; Escape, a click
 * elsewhere or moving focus out closes it. Small screens: the Menu button opens the whole menu as a drawer and the
 * same buttons open their panels in place. Without this script every panel is shown inline (css/layout/site-nav.css).
 */
((Drupal, once) => {
  const wide = window.matchMedia('(min-width: 64em)');

  Drupal.behaviors.marleneSiteNav = {
    attach(context) {
      once('marlene-site-nav', '[data-site-nav]', context).forEach((nav) => {
        const header = nav.closest('[data-site-header]');
        const drawerToggle = header.querySelector('[data-nav-toggle]');
        const toggles = [...nav.querySelectorAll('[data-nav-panel-toggle]')];

        const setPanel = (button, open) => {
          button.setAttribute('aria-expanded', String(open));
          button.closest('.site-nav__item').classList.toggle('is-open', open);
        };
        const closePanels = (except) => toggles.forEach((b) => b !== except && setPanel(b, false));
        const setDrawer = (open) => {
          drawerToggle.setAttribute('aria-expanded', String(open));
          header.classList.toggle('is-drawer-open', open);
          document.documentElement.classList.toggle('has-nav-drawer', open);
          if (!open) {
            closePanels();
          }
        };

        toggles.forEach((button) => {
          button.addEventListener('click', () => {
            const open = button.getAttribute('aria-expanded') !== 'true';
            if (wide.matches) {
              closePanels(button);
            }
            setPanel(button, open);
          });
        });

        drawerToggle.hidden = false;
        drawerToggle.addEventListener('click', () => {
          setDrawer(drawerToggle.getAttribute('aria-expanded') !== 'true');
        });

        // Escape closes the open panel (returning focus to its button), then the drawer.
        header.addEventListener('keydown', (event) => {
          if (event.key !== 'Escape') {
            return;
          }
          const open = toggles.find((b) => b.getAttribute('aria-expanded') === 'true');
          if (open) {
            setPanel(open, false);
            open.focus();
          }
          else if (header.classList.contains('is-drawer-open')) {
            setDrawer(false);
            drawerToggle.focus();
          }
        });

        // On wide screens a panel closes when the click or focus goes elsewhere.
        document.addEventListener('click', (event) => {
          if (wide.matches && !nav.contains(event.target)) {
            closePanels();
          }
        });
        nav.addEventListener('focusout', (event) => {
          if (wide.matches && event.relatedTarget && !nav.contains(event.relatedTarget)) {
            closePanels();
          }
        });

        // Crossing the breakpoint starts clean.
        wide.addEventListener('change', () => setDrawer(false));
      });
    },
  };
})(Drupal, once);
