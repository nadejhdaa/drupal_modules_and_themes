(function ($, Drupal, once, drupalSettings) {

  if(Drupal.AjaxCommands){

    Drupal.AjaxCommands.prototype.updWebform = function(ajax, response, status){
      var webform_id = response.webform_id;
      var params = drupalSettings.params;
      var webform_class = '.webform-subdemosion-' + webform_id.split('_').join('-') + '-form';
      var form = $(webform_class);

      $(form).find('input[name="doctor"]').val(params.fio);
      $(form).find('input[name="specialization"]').val(params.specialization);
      $(form).find('input[name="department"]').val(params.department);
      $(form).find('input[name="date"]').val(params.slot.date);
      $(form).find('input[name="time"]').val(params.slot.time);

      if ('slot_str' in params) {
        var slot_str = params.slot_str;
        $(form).find('input[name="services"]').val(slot_str);
      }
    }

  }

} (jQuery, Drupal, once, drupalSettings));
