/**
 * @file
 * Test doctor_cart_form behaviors.
 */
(function ($, Drupal, once, drupalSettings) {

  'use strict';

  Drupal.behaviors.testDoctorCartForm = {
    attach (context, settings) {
      const datepickerid = '.doctor-datepicker-wrapper';
      const alt_field = 'input[data-drupal-selector="edit-date"]';
      const form = $('form[data-drupal-selector="test-doctor-cart-form"]');
      const select_service = $(form).find('select[name="service"]');

      const doctor_datepickers = once('days', datepickerid, context);
      const appoint_button = $(form).find('input[data-drupal-selector="edit-submit"]');
      const specialist = $('input[name="specialist"]').val();

      // Add inline datepicker.
      doctor_datepickers.forEach((doctor_datepicker) => {
        var selected_service = selectedService();

        var date = new Date();
        var day_month = date.getFullYear() + '' +  (date.getMonth() + 1).toString();

        var days_all = 'days' in drupalSettings ? drupalSettings['days'] : [];

        var available_dates = selected_service in days_all ? days_all[selected_service] : [];
        available_dates = Object.keys(available_dates).map((key) => available_dates[key]);

        var min_date = '';
        var max_date = '';

        if (available_dates.length > 0) {
          min_date = available_dates[0];
          let max_date_math = available_dates.slice(-1)[0] ;

          let max_date_arr = max_date_math.split('-');
          let max_year = max_date_arr[0];
          let max_month = max_date_arr[1] - 1;
          let max_date_day = max_date_arr[2];

          max_date = new Date(max_year, max_month, max_date_day);

          $.datepicker.regional['ru'] = {
            closeText: 'Закрыть',
            currentText: 'Сегодня',
            monthNames: ['Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'],
            monthNamesShort: ['янв','фев','мар','апр','май','июн','июл','авг','сен','окт','ноя','дек'],
            dayNames: ['воскресенье','понедельник','вторник','среда','четверг','пятница','суббота'],
            dayNamesShort: ['Вс','Пн','Вт','Ср','Чт','Пт','Сб'],
            dayNamesMin: ['Вс','Пн','Вт','Ср','Чт','Пт','Сб'],
            firstDay: 1,
          };

          $.datepicker.setDefaults($.datepicker.regional['ru']);

          const calendar_options = {
            dateFormat: 'dd.mm.yy',
            altField: alt_field,
            altFormat: 'dd.mm.yy',
            inline: true,
            onSelect: changeDate,
            beforeShowDay: function(d) {
              var formatted = formatDate(d);

              if ($.inArray(formatted, available_dates) != -1) {
                return [true, "","Available"];
              } else{
                return [false,"","unAvailable"];
              }
            },
            maxDate: max_date,
          };

          const calendar = $(doctor_datepicker).datepicker(calendar_options);
        }

      });

      //////////////////////////
      function filterDays(specialist, selected_service) {
        var current_time = new Date();
        var current_month = current_time.getMonth() < 9 ? '0' + current_time.getMonth() + 1 : current_time.getMonth() + 1;
        var current_year_month = current_time.getFullYear() + '-' + current_month;

        var not_filtered_days = drupalSettings.days[selected_service];
        var days_by_month = {};

        not_filtered_days.forEach((date, i) => {
          if (date.substr(0, 7) !== current_year_month) {
            let year_month = date.substr(0, 7);
            if (!days_by_month[year_month]) {
              days_by_month[year_month] = [];
            }
            else {
              days_by_month[year_month].push(date);
            }
          }
        });

        var days_by_month_length = Object.keys(days_by_month).length;

        if (days_by_month_length > 0) {
          for (const [month_year, month_days] of Object.entries(days_by_month)) {
            var url = `/filter-days-by-month/${specialist}/${selected_service}`;
            var data = { month_days: month_days };
            $.ajax({
              url: url,
              type: 'POST',
              dataType: 'json',
              data: data
            })
            .done(function(response) {
              let filtered_month_dates = response.month_days;
              $(datepickerid).datepicker("refresh");
            });

          };
        }
      }

      // Trigger change on input[name="date"] for start "::changeDateAjaxCallback".
      function changeDate() {
        $(alt_field).trigger('change');
      	return false;
      }

      // Check if date in doctor days.
      function selectedService() {
        return $(select_service).find(":selected").val();
      }

      // Update selected date in form.
      $.fn.setDefaultDateAjaxCallback = function(date) {
        $(datepickerid).datepicker( 'setDate' , date);
        $(appoint_button).attr( 'value', Drupal.t('Appoint on @date', { '@date': date }) );
      };


    }
  };

  /**
   * Override AJAX "beforeSerialize" callback.
   */
  var originalAjaxBeforeSerialize = Drupal.Ajax.prototype.beforeSerialize;
  Drupal.Ajax.prototype.beforeSerialize = function (element, options) {
    // Change request url to url from element data-ajax-url attribute
    if (this.element) {
      var elementAjaxUrl = this.element.dataset.ajaxUrl;
      if (elementAjaxUrl) {
        var wrapperFormatIndex = options.url.indexOf(Drupal.ajax.WRAPPER_FORMAT);
        if (wrapperFormatIndex > 0) {
          elementAjaxUrl += (elementAjaxUrl.indexOf('?') === -1) ? '?' : '&';
          elementAjaxUrl += options.url.substring(wrapperFormatIndex);
        }
        options.url = elementAjaxUrl;
      }
    }

    // Call original callback
    return originalAjaxBeforeSerialize.apply(this, arguments);
  };

} (jQuery, Drupal, once, drupalSettings));


function formatDate(date) {
  var yyyy = date.getFullYear().toString();
  var mm = date.getMonth() < 9 ? '0' + (date.getMonth() + 1) : (date.getMonth() + 1).toString();
  var dd = date.getDate().toString();
  return yyyy + '-' + (mm[1] ? mm : '-' + mm[0]) + '-' + (dd[1] ? dd : '0' + dd[0]);
}
