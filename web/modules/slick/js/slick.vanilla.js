/**
 * @file
 * Provides Slick vanilla where options can be directly injected via data-slick.
 */

(function ($, Drupal, _d) {

  'use strict';

  var _id = 'slick-vanilla';
  var _mounted = _id + '--on';
  // @fixme typo at 3.x, should be BEM modifier: .slick--vanilla.
  var _element = '.' + _id + ':not(.' + _mounted + ')';

  /**
   * Slick utility functions.
   *
   * @param {HTMLElement} elm
   *   The slick HTML element.
   */
  function doSlickVanilla(elm) {
    var $elm = $(elm);
    $elm.slick();
    $elm.addClass(_mounted);
  }

  /**
   * Attaches slick behavior to HTML element identified by .slick-vanilla.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.slickVanilla = {
    attach: function (context) {

      // Weirdo: context may be null after Colorbox close.
      context = context || document;

      // jQuery may pass its object as non-expected context identified by length.
      context = 'length' in context ? context[0] : context;
      context = context instanceof HTMLDocument ? context : document;

      // Prevents potential missing due to the newly added sitewide option.
      var elms = context.querySelectorAll(_element);
      if (elms.length) {
        _d.once(_d.forEach(elms, doSlickVanilla));
      }
    }
  };

})(jQuery, Drupal, dBlazy);
