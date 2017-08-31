(($, { ajax, behaviors }) => {
  behaviors.layoutBuilder = {
    attach(context) {
      $(context).find('.layout__region').sortable({
        items: '> .draggable',
        connectWith: '.layout__region',

        /**
         * Updates the layout with the new position of the block.
         *
         * @param {jQuery.Event} event
         *   The jQuery Event object.
         * @param {Object} ui
         *   An object containing information about the item being sorted.
         */
        update(event, ui) {
          const data = {
            region_to: $(this).data('region'),
            block_uuid: ui.item.data('layout-block-uuid'),
            delta_to: ui.item.closest('[data-layout-delta]').data('layout-delta'),
            preceding_block_uuid: ui.item.prev('[data-layout-block-uuid]').data('layout-block-uuid'),
          };

          // @todo What does this condition guard against?
          if (this === ui.item.parent()[0]) {
            if (ui.sender) {
              data.region_from = ui.sender.data('region');
              data.delta_from = ui.sender.closest('[data-layout-delta]').data('layout-delta');
            }
            else {
              data.region_from = data.region_to;
              data.delta_from = data.delta_to;
            }

            ajax({
              url: ui.item.closest('[data-layout-update-url]').data('layout-update-url'),
              submit: data,
            }).execute();
          }
        },
      });
    },
  };
})(jQuery, Drupal);
