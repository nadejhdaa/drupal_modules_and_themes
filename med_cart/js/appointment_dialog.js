/**
 * @file
 * ENC appointment dialog behaviors.
 */
(function ($, Drupal, once, drupalSettings) {

  'use strict';

  Drupal.behaviors.appointmentDialog = {
    attach (context, settings) {

      $(context).on('click', '.leave-a-request-link', function(event) {
        event.preventDefault();

        Drupal.dialog(document.getElementById('drupal-modal')).close();

        var frontpageModal = Drupal.dialog('<div>Modal content</div>', {
          title: 'Modal on frontpage',
          dialogClass: 'front-modal',
          width: 400,
          height: 400,
          autoResize: true,
          close: function (event) {
            // Удаляем элемент который использовался для содержимого.
            // $(event.target).remove();
          }
        });
        // Отображает модальное окно с overlay.
        frontpageModal.showModal();
        return false;
      });


    }
  };


} (jQuery, Drupal, once, drupalSettings));
