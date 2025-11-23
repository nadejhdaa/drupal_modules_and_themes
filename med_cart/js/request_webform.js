/**
 * @file
 * ENC cart_page_form behaviors.
 */
(function ($, Drupal, once) {

  'use strict';

  Drupal.behaviors.requestWebform = {
    attach (context, settings) {
      var originalAjaxSuccess = Drupal.Ajax.prototype.success;

      Drupal.Ajax.prototype.success = function (response, status) {



        var originalAjaxSuccessPromise = originalAjaxSuccess.apply(this, arguments);
        return originalAjaxSuccessPromise.then(function () {
          console.log('ajax')
          response.forEach((item, i) => {

            console.log('ajax start')
            if (item.selector == '#webform-submission-zayavka-na-priem-form-ajax' && item.command == 'insert') {
              $('.block-enc-cart-top .count').replaceWith('<div class="count"></div>');
            }
          });
        });
      };

    }
  };

} (jQuery, Drupal, once));
