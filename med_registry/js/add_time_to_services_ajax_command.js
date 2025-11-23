(function ($, Drupal, once, drupalSettings) {

  if(Drupal.AjaxCommands){

    Drupal.AjaxCommands.prototype.AddTimeToServicesCommand = function(ajax, response, status){
      var services = response.services;
      var selector = response.selector;

      var wrapper = document.querySelector(selector);

      if (wrapper && services.length > 0) {
        services.forEach((service) => {
          let service_div = wrapper.querySelector('[data-service="' + service + '"]');

          if (service_div) {
            let closest_date = service_div.getAttribute('data-closest-date');

            if (closest_date) {
              let data = {
                service : service,
                date : closest_date
              };

              let url = '/med-mis-data-get-service-closest-time';
              $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                data: data
              })
              .done(function(response) {
                if (response.time) {
                  service_div.querySelector('.service-closest-time').textContent = response.time;
                  service_div.querySelector('.service-closest-day-info').classList.remove('hidden');
                }
                else {
                  service_div.querySelector('.service-closest-day-info').classList.add('hidden');
                }

                if (response.date) {
                  service_div.querySelector('.service-closest-date').textContent = response.date;
                }

              });
            }
          }
        });
      }















    }

  }

} (jQuery, Drupal, once, drupalSettings));
