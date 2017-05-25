(function ($, Drupal) {

  Drupal.behaviors.layoutBuilder = {

    attach: function (context) {
      $(context).find('.layout__region').sortable({
        items: '> .draggable',
        connectWith: '.layout__region',
        update: function (event, ui) {
          let data = {
            region_to: $(this).data('region'),
            block_uuid: ui.item.data('layout-block-uuid'),
            delta_to: ui.item.closest('[data-layout-delta]').data('layout-delta'),
            preceding_block_uuid: ui.item.prev('[data-layout-block-uuid]').data('layout-block-uuid')
          };

          if (ui.sender) {
            data.region_from = ui.sender.data('region');
            data.delta_from = ui.sender.closest('[data-layout-delta]').data('layout-delta');
          }
          else {
            data.region_from = data.region_to;
            data.delta_from = data.delta_to;
          }

          let url = ui.item.closest('[data-layout-update-url]').data('layout-update-url');

          $.ajax(url, {
            data: JSON.stringify(data),
            method: 'POST'
          });
        }
      });
    }

  };

})(jQuery, Drupal);
