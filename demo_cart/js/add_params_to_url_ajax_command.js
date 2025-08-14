(function ($, Drupal, once, drupalSettings) {
  if(Drupal.AjaxCommands){

    Drupal.AjaxCommands.prototype.addParamsToUrl = function(ajax, response, status){
      var params = response.params;
      var size = Object.keys(params).length;

      if (size > 0) {
        if ('service' in params) {
          const state = { page_id: 1, user_id: 5 };
          var url = window.location.pathname + '?';

          var params_arr = [];

          for (let [key, value] of Object.entries(params)) {
            params_arr.push(key + '=' + encodeURIComponent(value));
          }

          var params_str = params_arr.join('&');
          url += params_str;

          var current_url = document.URL;
          var url_parts = current_url.split('#');

          if (url_parts.length > 1) {
            var anchor_part = url_parts[1];
            var anchor_parts = anchor_part.split('?');
            var anchor = anchor_parts[0];

            url += '#' + anchor;
          }

          history.pushState({}, 'selected service', url);
        }
      }
    }

  }

} (jQuery, Drupal, once, drupalSettings));
