(function ($) {

    $(document).ready(function () {

        $(document).ajaxComplete(function () {

            $('select.components-select-control__input option').filter(function () {
                return $(this).text() === 'در هفته';
            }).remove();

        });

        $(".woocommerce-setting").each(function () {
            if ($(this).find(".woocommerce-setting__label").text().trim() === "محدودۀ تاریخ پیش‌فرض:") {
                $(this).hide();
            }
        });


    });

    $(document).on('click', '.woocommerce-dropdown-button', function () {

        $('[id^="tab-panel-"][id$="-custom"]').css({'display': 'none'})
        $(".woocommerce-filters-date__tabs").find(".components-tab-panel__tabs").css("display", "none");
        let selection_container = $(".woocommerce-segmented-selection__container");
        $('.woocommerce-segmented-selection__container input[id^="week_"], .woocommerce-segmented-selection__container input[id^="last_week_"]')
            .closest('.woocommerce-segmented-selection__item')
            .hide();

        let compare_selection_container = selection_container.eq(1);
        compare_selection_container.children().eq(1).css('display', 'none');
        compare_selection_container.css('grid-template-columns', '1fr');
        let previous_period = compare_selection_container.children().eq(0).find('.woocommerce-segmented-selection__label');
        previous_period.css('text-align', 'center');

    });


})(jQuery);