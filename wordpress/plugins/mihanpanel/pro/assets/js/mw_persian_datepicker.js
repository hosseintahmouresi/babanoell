jQuery(document).ready(function($){
    let datepickerField = $('.mw_datepicker input[mw_target]'),
        datepickerAltField = datepickerField.next('input');
    datepickerField.persianDatepicker({
        autoClose: true,
        calendarType: mwp_data.calendar_type,
        format: 'L',
        altField: datepickerAltField,
        altFieldFormatter: function(unixDate){
            let newDate = new persianDate(unixDate);
            return newDate.unix()
        }
    });
});