/**
 * @file
 * Runs the D6 Lightbox2 script (lib/lightbox2-6.x-1.12, unchanged) on Drupal 11.
 *
 * It was written for D6's jQuery 1.2: a global $, $.browser, Drupal.settings and behaviors as functions. Drupal 11
 * calls jQuery.noConflict(), jQuery dropped $.browser (an empty one means "not an old browser"), the settings are
 * drupalSettings and a behavior is {attach}.
 */
(function (jQuery, Drupal, drupalSettings) {
  window.$ = jQuery;
  jQuery.browser = jQuery.browser || {};
  Drupal.settings = Drupal.settings || Object.assign({ basePath: drupalSettings.path.baseUrl }, drupalSettings);

  const init = Drupal.behaviors.initLightbox;
  Drupal.behaviors.initLightbox = {
    attach(context) {
      init(context);
    },
  };
})(jQuery, Drupal, drupalSettings);
