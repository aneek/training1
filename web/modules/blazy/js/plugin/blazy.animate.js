/**
 * @file
 * Provides animate extension for dBlazy when using blur or animate.css.
 *
 * Alternative for native Element.animate, only with CSS animation instead.
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Element/animate
 */

(function ($) {

  'use strict';

  var _1px = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

  /**
   * A simple wrapper to animate anything using animate.css.
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string|Function} cb
   *   Any custom animation name, fallbacks to [data-animation], or a callback.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function animate(els, cb) {
    var me = this;

    var chainCallback = function (el) {
      var _set = el.dataset;

      if (!$.isElm(el) || !_set) {
        return me;
      }

      var $el = $(el);
      var animation = _set.animation;

      if ($.isStr(cb)) {
        animation = cb;
      }

      if (!animation) {
        return me;
      }

      var _ani = 'animation';
      var _animated = 'animated';
      var _aniEnd = _ani + 'end.' + animation;
      var _style = el.style;
      var _blur = 'blur';
      var _bblur = 'b-' + _blur;
      var classes = _animated + ' ' + animation;
      var props = [
        _ani,
        _ani + '-duration',
        _ani + '-delay',
        _ani + '-iteration-count'
      ];

      $el.addClass(classes);

      $.each(['Duration', 'Delay', 'IterationCount'], function (key) {
        var _aniKey = _ani + key;
        if (_set && _aniKey in _set) {
          _style[_aniKey] = _set[_aniKey];
        }
      });

      // Supports both BG and regular image.
      var cn = $.closest(el, '.media') || el;
      var bg = $el.hasClass('b-bg');
      var isBlur = animation === _blur;
      var an = el;

      // The animated blur is image not this container, except a background.
      if (isBlur && !bg) {
        an = $.find(cn, 'img:not(.' + _bblur + ')') || an;
      }

      function ended(e) {
        $el.addClass('is-b-' + _animated)
          .removeClass(classes)
          .removeAttr(props, 'data-');

        $.each(props, function (key) {
          _style.removeProperty(key);
        });

        if ($.isFun(cb)) {
          cb(e);
        }

        if (isBlur) {
          var elBlur = $.find(cn, 'img.' + _bblur);
          if ($.isElm(elBlur)) {
            elBlur.src = _1px;
          }
        }
      }

      return $.one(an, _aniEnd, ended, false);
    };

    return $.chain(els, chainCallback);
  }

  $.animate = animate.bind($);
  $.fn.animate = function (animation) {
    return animate(this, animation);
  };

}(dBlazy));
