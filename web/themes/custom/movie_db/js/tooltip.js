/**
 * @file
 * Global utilities.
 *
 */
(function($, Drupal) {

  'use strict';

  Drupal.behaviors.movie_db = {
    attach: function(context, settings) {
      let tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
      let tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
      })
    }
  };

})(jQuery, Drupal);
