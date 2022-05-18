/**
 * @file
 * Provides loading extension for dBlazy.
 */

(function ($) {

  'use strict';

  /**
   * Removes common loading indicator classes.
   *
   * @private
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The loading HTML element(s), or dBlazy instance.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function unloading(els) {
    var chainCallback = function (el) {
      var _loading = 'loading';
      // The .b-lazy element can be attached to IMG, or DIV as CSS background.
      // The .(*)loading can be .media, .grid, .slide__content, .box, etc.
      var loaders = [el, $.closest(el, '[class*="' + _loading + '"]')];

      $.each(loaders, function (loader) {
        if ($.isElm(loader)) {
          var name = loader.className;
          if ($.contains(name, _loading)) {
            loader.className = name.replace(/(\S+)loading/g, '');
          }
        }
      });
    };

    return $.chain(els, chainCallback);
  }

  $.unloading = unloading;
  $.fn.unloading = function () {
    return unloading(this);
  };

}(dBlazy));
