(function ($, Drupal, once, drupalSettings) {

  if(Drupal.AjaxCommands){

    Drupal.AjaxCommands.prototype.SetUrlParamsCommand = function(ajax, response, status){

      if (response.params) {
        const params = response.params;

        var url = window.location.pathname + (params.length == 0 ? '' : '?');

        var params_arr = [];

        for (let [key, value] of Object.entries(params)) {
          params_arr.push(key + '=' + encodeURIComponent(value));
        }

        var params_str = params_arr.join('&');
        url += params_str;

        history.pushState({}, 'registry_form params', url);
      }

    }
  }

} (jQuery, Drupal, once, drupalSettings));
