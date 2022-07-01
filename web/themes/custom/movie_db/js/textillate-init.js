(function ($, Drupal, drupalSettings, once){

  'use strict';

  Drupal.behaviors.movie_dbTextillate = {
    attach: function (context, settings){
      let enabled = settings.movie_db.textillate_settings.enabled;
      let element = ( settings.movie_db.textillate_settings.element ) ? settings.movie_db.textillate_settings.element : 'h1';
      if (enabled){
        once('textillate-processed', element, context).forEach(function (element){
          //Apply the myCustomBehavior effect to the elements only once.
          $(element).textillate();
        });
      }
    }
  };
})(jQuery, Drupal, drupalSettings, once);
