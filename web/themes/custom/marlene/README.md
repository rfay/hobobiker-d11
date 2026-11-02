# Marlene

The modern look for hobobiker.com: responsive, light and dark, with a banner of Randy and Nancy before the
Cordillera Blanca. Installed and made the default by `scripts/95-marlene.php`. The old look, `hobo_golden`, stays
installed (`drush config:set system.theme default hobo_golden` goes back).

Named for Marlene Wanberg, whose [UI Theme Explorer](https://mwanberg.github.io/ai-playground/ui-theme-explorer/)
([source](https://github.com/mwanberg/ai-playground)) is the model for its design tokens.

## Why it is built this way

- **Generated from core's Starterkit** (`vendor/bin/dr generate-theme marlene`, Drupal 11.4), not a sub-theme:
  `base theme: false`, so a core release can't change it underneath. Starterkit's templates and
  `css/components/` are core's maintained baseline; this theme's own layer sits on top in `css/base`,
  `css/layout` and `css/theme`, and overrides only the templates it needs.
- **Design tokens** (`css/base/tokens.css`): every stylesheet reads only the custom properties defined there
  (color, type, shape, elevation, motion), with dark values for `prefers-color-scheme` and for the visitor's
  explicit choice (`html[data-color-scheme]`). To retune the palette or type, change that file only.
- **No build step, no framework**: plain CSS (custom properties, grid, `:has()`, `color-mix()`) and two small
  vanilla scripts, attached as Drupal behaviors.
- **Self-hosted fonts** (`fonts/`, SIL OFL): Fraunces for headings, Figtree for text; Latin and Latin Extended.

## Map

| Piece | Where |
| --- | --- |
| Header, banner/hero, main column, footer | `templates/layout/page.html.twig`, `css/layout/layout.css` |
| Main menu: disclosure dropdowns on wide screens, a drawer on small ones | `templates/navigation/menu--main.html.twig`, `css/layout/site-nav.css`, `js/site-nav.js` |
| Light / dark / automatic toggle | `js/color-scheme.js`, inline script in `templates/layout/html.html.twig` |
| Nodes: byline, tag chips, the labelled fields as a facts grid, the inline "read more" | `templates/content/node.html.twig`, `src/Hook/MarleneHooks.php`, `css/theme/content.css` |
| Front page panel (story first on phones) | `css/theme/front.css` |
| Breadcrumb, pager, tabs, messages, search | `css/theme/components.css` |
| Banner images | `images/banner/` (from `2821697987_Randy_and_Nancy_in_front_of_Cordillera_Blanca_peak.jpg`: the whole photo for the front page, a crop of the peaks for the band on other pages) |
| Favicon set | `images/favicon/favicon.svg` (the source), its PNGs, `favicon.ico`, `site.webmanifest` |

PHP lives in `src/Hook/MarleneHooks.php` as object-oriented hooks with injected services.

## Regenerating the images

The banner and favicon renderings come from their sources with ImageMagick (in the DDEV web container) and, for
the favicon PNGs, a headless Chromium, since ImageMagick's SVG renderer drops gradients. Widths are listed in
`MarleneHooks::BANNERS`.
