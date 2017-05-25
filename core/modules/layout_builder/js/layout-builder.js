(function ($, Drupal) {

  Drupal.behaviors.layoutBuilder = {

    attach: function (context) {
      $(context).find('.layout__region').sortable({
        items: '> .draggable',
        connectWith: '.layout__region'
      });
    }

  };

})(jQuery, Drupal);
