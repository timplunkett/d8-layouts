(function ($, Drupal) {

  Drupal.behaviors.layoutBuilder = {

    attach: function (context) {
      $(context).find('.layout__region').sortable({
        items: '> .draggable',
        connectWith: '.layout__region',
        receive: function (event, ui) {
          let data = {};
          data.region_from = ui.sender.data('region');
          data.region_to = $(this).data('region');
          data.block_uuid = ui.item.data('layout-block-uuid');
          data.delta_from = ui.sender.closest('[data-layout-delta]').data('layout-delta');
          data.delta_to = ui.item.closest('[data-layout-delta]').data('layout-delta');
          data.preceding_block_uuid = ui.item.prev('[data-layout-block-uuid]').data('layout-block-uuid');
          console.log(data);
        },
      });
    }

  };

})(jQuery, Drupal);
