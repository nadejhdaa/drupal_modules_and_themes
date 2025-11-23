/**
 * @file
 * ENC registry_form behaviors.
 */
(function ($, Drupal, once, drupalSettings) {

  'use strict';

  Drupal.behaviors.medRegistryForm = {
    attach (context, settings) {

      var form = once('med-lk-registry-form', document.querySelector('form.med-lk-registry-form'));

      if (form.length) {
        form = form.shift();
        var submit_button = form.querySelector('[data-drupal-selector="edit-submit"]');

        // Set autoselect all for autocomplete inputs.
        var autocomplete_inputs = form.querySelectorAll('[data-autocomplete-path]');

        autocomplete_inputs.forEach((autocomplete_input) => {
          autocomplete_input.addEventListener('click', function() {
            autocomplete_input.select();
          })
        });

        const tabs = form.querySelectorAll('.registry-tab');
        tabs.forEach((tab) => {
          let tab_value = tab.value;
          setItemsClosestTime(tab_value);
        });

        let date_range_pickers = form.getElementsByClassName('date-range-picker');
        date_range_pickers = Array.from(date_range_pickers);

        if (date_range_pickers.length) {
          buildDatePicker(date_range_pickers);
        }

        function buildDatePicker(date_range_pickers) {
          date_range_pickers.forEach((date_range_picker) => {

            const date = new Date();
            let day = date.getDate();
            let month = date.getMonth() + 1;
            let year = date.getFullYear();
            let current_date = `${year}-${month}-${day}`;

            const picker = new Litepicker({
              element: date_range_picker,
              singleMode: false,
              format: "DD.MM.YYYY",
              lang: "ru-RU",
              minDate: current_date,
              numberOfColumns: 2,
              numberOfMonths: 2,
              autoRefresh: true,
              autoApply: true,
              allowRepick: true,
              setup: (picker) => {
                picker.on('selected', function (start, end) {
                  form.querySelector('input[data-drupal-selector="edit-submit"]').dispatchEvent(new Event('mousedown'));
                });
              }
            })
          });
        }

        function setItemsClosestTime(tab) {
          // Если выбран таб "Выбор специалиста".
          if (tab == 'specialist') {
            setClosestTimeForDoctors();
          }

          // Если выбран таб "Выбор услуги".
          else {
            setClosestTimeForServices();
          }
        }

        // Добавить время ближайшее приема каждому специалисту в форме.
        function setClosestTimeForDoctors() {

          let doctor_items = form.querySelectorAll('ul.selected-doctors__doctors-list li');

          if (doctor_items) {
            doctor_items.forEach((doctor_item) => {
              let data_div = doctor_item.querySelector('[data-specialist]');

              if (data_div) {
                let specialist = data_div.getAttribute('data-specialist');
                let closest_date = data_div.getAttribute('data-closest-date');

                if (closest_date && specialist) {
                  let data = {
                    specialist : specialist,
                    date : closest_date
                  };

                  if (data_div.getAttribute('data-selected-service')) {
                    data['service'] = data_div.getAttribute('data-selected-service');
                  }

                  let url = '/med-mis-data-get-service-closest-time';
                  $.ajax({
                    url: url,
                    type: 'POST',
                    dataType: 'json',
                    data: data
                  })
                  .done(function(response) {
                    if (response.time) {
                      doctor_item.querySelector('.specialist-closest-time').textContent = response.time;

                      doctor_item.querySelector('.specialist-closest-day-info').classList.remove('hidden');
                      doctor_item.querySelector('.specialist-no-days').classList.add('hidden');
                    }
                    else {
                      doctor_item.querySelector('.specialist-closest-day-info').classList.add('hidden');
                      doctor_item.querySelector('.specialist-no-days').classList.remove('hidden');
                    }

                    if (response.date) {
                      doctor_item.querySelector('.specialist-closest-date').textContent = response.date;
                    }

                  });
                }
              }
            })
          };
        }

        // Обновить время приема для услуг.
        function setClosestTimeForServices() {
          if (form.getElementsByClassName('selected-doctors_services-list').length) {

            let service_items = form.querySelector('.selected-doctors_services-list').getElementsByClassName('selected-services__item');

            if (service_items.length) {
              service_items.forEach((service_item) => {
                let data_div = service_item.querySelector('[data-closest-date]');

                if (data_div) {
                  let service = data_div.getAttribute('data-service');
                  let closest_date = data_div.getAttribute('data-closest-date');
                  let time_div = data_div.querySelector('.service-closest-time');

                  if (closest_date && service && closest_date !== '' && time_div.textContent.trim() === "") {
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
                        service_item.querySelector('.service-closest-time').textContent = response.time;
                        service_item.querySelector('.service-closest-day-info').classList.remove('hidden');
                      }
                      else {
                        service_item.querySelector('.service-closest-day-info').classList.add('hidden');
                      }

                      if (response.date) {
                        service_item.querySelector('.service-closest-date').textContent = response.date;
                      }
                    });
                  }
                }
              });
            }
          }
        }




      }


    }
  };



} (jQuery, Drupal, once, drupalSettings));
