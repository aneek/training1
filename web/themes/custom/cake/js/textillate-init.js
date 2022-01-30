(function ($, Drupal, drupalSettings, once) {
  'use strict';
  Drupal.behaviors.cakeTextillate = {
    attach: function (context, settings) {
      let enabled = settings.cake.textillate_settings.enabled;
      let elm = (settings.cake.textillate_settings.element ) ? settings.cake.textillate_settings.element : 'h1';
      if (enabled) {
        once('textillate-processed', elm, context).forEach(function (element) {
          // Apply the myCustomBehaviour effect to the elements only once.
          $(element).textillate();
        });
      }
    }
  };
})(jQuery, Drupal, drupalSettings, once);
