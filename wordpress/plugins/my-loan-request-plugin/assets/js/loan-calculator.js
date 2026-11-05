jQuery(document).ready(function($) {
    if ($('.mlr-calc-wrapper').length === 0) return;

    const allPlansData = mlrCalculatorAjax.plans;
    let selectedPlan = null;
    let currentAmount = 0;

    const planTabsContainer = $('#mlr_plan_tabs_container');
    const amountInput = $('#mlr_loan_amount');
    const amountSlider = $('#mlr_loan_slider');
    const resultDisplay = $('#mlr_result_display');
    const amountLabel = $('#mlr_amount_label');
    const sliderMinLabel = $('#mlr_slider_min_label');
    const sliderMaxLabel = $('#mlr_slider_max_label');
    const installmentsTabsContainer = $('#mlr_installments_tabs');
    const intervalWrapper = $('#calc_interval_wrapper_front');
    const intervalTabsContainer = $('#mlr_interval_tabs_container');
    const calculateBtn = $('#mlr_calculate_btn');
    const planSelectorMode = (mlrCalculatorAjax.selector_mode === 'dropdown') ? 'dropdown' : 'tabs';

    function formatNumber(num) {
        return new Intl.NumberFormat('fa-IR').format(num);
    }

    function parseFormattedNumber(str) {
        if (!str) return 0;
        const sanitizedStr = String(str).replace(/[٬,]/g, '');
        return parseInt(sanitizedStr, 10) || 0;
    }

    function escapeHtml(value) {
        return $('<div/>').text(value || '').html();
    }

    function escapeAttr(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function hasPlanConditions(conditionsHtml) {
        const html = String(conditionsHtml || '');
        return $.trim($('<div/>').html(html).text()) !== '' || /<(img|ul|ol|li|table|br|p|div|span|strong|em|b|i)\b/i.test(html);
    }

    function getPlanInfoAccordions() {
        return $('.mlr-calc-wrapper .mlr-plan-info-accordions').first();
    }

    function getPlanDocsAccordion() {
        return $('.mlr-calc-wrapper .mlr-plan-docs-accordion').not('.mlr-plan-conditions-accordion').first();
    }

    function getPlanConditionsAccordion() {
        return $('.mlr-calc-wrapper .mlr-plan-conditions-accordion').first();
    }

    function setPlanDocsAccordionCollapsed(accordion, collapsed) {
        if (!accordion || accordion.length === 0) return;
        accordion.attr('data-state', collapsed ? 'collapsed' : 'expanded');
        accordion.find('.mlr-plan-docs-toggle').first().attr('aria-expanded', collapsed ? 'false' : 'true');
        accordion.find('.mlr-plan-docs-panel').first().prop('hidden', collapsed);
    }

    function renderPlanRequiredDocumentsAccordion(planId, docs) {
        const wrapper = getPlanInfoAccordions();
        const accordion = getPlanDocsAccordion();
        if (accordion.length === 0 || !planId) {
            return;
        }

        const documents = Array.isArray(docs) ? docs : [];
        wrapper.css('display', 'grid');
        accordion.show();
        accordion.find('.mlr-plan-docs-status').hide().removeClass('is-error').empty();

        if (documents.length === 0) {
            accordion.find('.mlr-plan-docs-list').html('<div class="mlr-plan-docs-empty">برای این طرح مدرکی ثبت نشده است.</div>');
            setPlanDocsAccordionCollapsed(accordion, true);
            return;
        }

        let docsHtml = '';
        documents.forEach(function(doc) {
            const label = doc && (doc.label || doc.key) ? (doc.label || doc.key) : '';
            const description = doc && doc.description ? doc.description : '';
            if (!label) return;
            docsHtml += '<div class="mlr-plan-doc-item' + (description ? ' has-description' : '') + '"' + (description ? ' tabindex="0" title="' + escapeAttr(description) + '"' : '') + '>';
            docsHtml += '<span class="mlr-plan-doc-check" aria-hidden="true"></span>';
            docsHtml += '<span class="mlr-plan-doc-text"><span class="mlr-plan-doc-label">' + escapeHtml(label) + '</span>';
            if (description) {
                docsHtml += '<span class="mlr-plan-doc-description">' + escapeHtml(description) + '</span>';
            }
            docsHtml += '</span>';
            if (description) {
                docsHtml += '<span class="mlr-plan-doc-hint" aria-hidden="true">؟</span>';
            }
            docsHtml += '</div>';
        });
        accordion.find('.mlr-plan-docs-list').html(docsHtml || '<div class="mlr-plan-docs-empty">برای این طرح مدرکی ثبت نشده است.</div>');
        setPlanDocsAccordionCollapsed(accordion, true);
    }

    function renderPlanConditionsAccordion(planId, conditionsHtml) {
        const wrapper = getPlanInfoAccordions();
        const accordion = getPlanConditionsAccordion();
        if (accordion.length === 0 || !planId) {
            return;
        }

        wrapper.css('display', 'grid');
        accordion.show();
        accordion.find('.mlr-plan-conditions-status').hide().removeClass('is-error').empty();

        if (!hasPlanConditions(conditionsHtml)) {
            accordion.find('.mlr-plan-conditions-content').html('<div class="mlr-plan-docs-empty">برای این طرح شرایط خاصی ثبت نشده است.</div>');
            setPlanDocsAccordionCollapsed(accordion, true);
            return;
        }

        accordion.find('.mlr-plan-conditions-content').html('<div class="mlr-plan-conditions-body">' + conditionsHtml + '</div>');
        setPlanDocsAccordionCollapsed(accordion, true);
    }

    $(document).off('click.mlrCalcPlanDocsAccordion', '.mlr-calc-wrapper .mlr-plan-docs-toggle').on('click.mlrCalcPlanDocsAccordion', '.mlr-calc-wrapper .mlr-plan-docs-toggle', function() {
        const accordion = $(this).closest('.mlr-plan-docs-accordion');
        setPlanDocsAccordionCollapsed(accordion, $(this).attr('aria-expanded') === 'true');
    });

    function initializePlanTabs() {
        planTabsContainer.empty();

        if (planSelectorMode === 'dropdown') {
            const dropdown = $('<div>', {
                class: 'mlr-calc-plan-dropdown',
                'aria-expanded': 'false'
            });
            const trigger = $('<button>', {
                type: 'button',
                class: 'mlr-calc-plan-trigger',
                'aria-haspopup': 'listbox'
            });
            trigger.append($('<span>', {
                class: 'mlr-calc-plan-trigger-text',
                text: allPlansData.length ? allPlansData[0].name : 'انتخاب طرح تسهیلات'
            }));
            trigger.append($('<span>', {
                class: 'mlr-calc-plan-trigger-icon',
                html: '<svg class="mlr-calc-plan-trigger-chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7.4 8.6 12 13.2l4.6-4.6L18 10l-6 6-6-6z"></path></svg>'
            }));

            const menu = $('<div>', {
                class: 'mlr-calc-plan-menu',
                role: 'listbox'
            });

            dropdown.append(trigger, menu);
            planTabsContainer.append(dropdown);

            allPlansData.forEach((plan, index) => {
                const button = $('<button>', {
                    type: 'button',
                    class: 'mlr-calc-tab plan-tab' + (index === 0 ? ' active' : ''),
                    'data-plan-id': plan.id,
                    role: 'option',
                    'aria-selected': index === 0 ? 'true' : 'false',
                    text: plan.name
                });
                menu.append(button);
            });
        } else {
            allPlansData.forEach((plan, index) => {
                const button = $('<button>', {
                    type: 'button',
                    class: 'mlr-calc-tab plan-tab' + (index === 0 ? ' active' : ''),
                    'data-plan-id': plan.id,
                    text: plan.name
                });
                planTabsContainer.append(button);
            });
        }

        if(allPlansData.length > 0) {
            updateUIForPlan(allPlansData[0]);
        }
    }

    function updateUIForPlan(plan) {
        selectedPlan = plan;
        const settings = plan.settings;
        const minAmount = parseInt(settings.min_amount) || 0;
        const maxAmount = parseInt(settings.max_amount) || 1000000;
        
        currentAmount = minAmount;

        amountLabel.text(plan.type === 'leasing' ? 'مبلغ خرید (ریال)' : 'مبلغ وام (ریال)');
        amountInput.val(formatNumber(currentAmount)).prop('disabled', false);
        amountSlider.attr({
            min: minAmount,
            max: maxAmount,
            value: currentAmount,
            step: 1000000
        }).prop('disabled', false);
        sliderMinLabel.text(formatNumber(minAmount) + ' ریال');
        sliderMaxLabel.text(formatNumber(maxAmount) + ' ریال');

        installmentsTabsContainer.empty();
        if (settings.active_installments) {
            const installments = settings.active_installments.split(',');
            installments.forEach((months, index) => {
                const button = $('<button>', {
                    class: 'mlr-calc-tab installment-tab' + (index === 0 ? ' active' : ''),
                    'data-months': $.trim(months),
                    text: $.trim(months) + ' ماهه'
                });
                installmentsTabsContainer.append(button);
            });
        }
        
        intervalTabsContainer.empty();
        if (plan.type === 'leasing') {
            intervalWrapper.slideDown();
            if (settings.min_interval && settings.max_interval) {
                for (let i = parseInt(settings.min_interval); i <= parseInt(settings.max_interval); i++) {
                     const button = $('<button>', {
                        class: 'mlr-calc-tab interval-tab' + (i === parseInt(settings.min_interval) ? ' active' : ''),
                        'data-interval': i,
                        text: i + ' ماه'
                    });
                    intervalTabsContainer.append(button);
                }
            }
        } else {
            intervalWrapper.slideUp();
        }
        
        renderPlanRequiredDocumentsAccordion(plan.id, plan.required_documents || []);
        renderPlanConditionsAccordion(plan.id, plan.plan_conditions || '');
        resultDisplay.html('<p class="mlr-calc-placeholder">برای مشاهده نتایج، روی دکمه محاسبه کلیک کنید.</p>');
    }
    
    planTabsContainer.on('click', '.mlr-calc-plan-trigger', function() {
        const dropdown = $(this).closest('.mlr-calc-plan-dropdown');
        const isOpen = dropdown.hasClass('is-open');
        $('.mlr-calc-plan-dropdown').removeClass('is-open').attr('aria-expanded', 'false');
        dropdown.toggleClass('is-open', !isOpen).attr('aria-expanded', !isOpen ? 'true' : 'false');
    });

    $(document).on('click', function(event) {
        if ($(event.target).closest('#mlr_plan_tabs_container').length === 0) {
            $('.mlr-calc-plan-dropdown').removeClass('is-open').attr('aria-expanded', 'false');
        }
    });

    planTabsContainer.on('keydown', '.mlr-calc-plan-trigger', function(event) {
        if (event.key === 'Escape') {
            $(this).closest('.mlr-calc-plan-dropdown').removeClass('is-open').attr('aria-expanded', 'false');
        }
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            $(this).trigger('click');
        }
    });

    planTabsContainer.on('click', '.plan-tab', function() {
        planTabsContainer.find('.plan-tab').removeClass('active').attr('aria-selected', 'false');
        $(this).addClass('active').attr('aria-selected', 'true');
        const planId = $(this).data('plan-id');
        const plan = allPlansData.find(p => p.id == planId);
        if(plan) {
            updateUIForPlan(plan);
            if (planSelectorMode === 'dropdown') {
                planTabsContainer.find('.mlr-calc-plan-trigger-text').text(plan.name);
                planTabsContainer.find('.mlr-calc-plan-dropdown').removeClass('is-open').attr('aria-expanded', 'false');
            }
        }
    });

    amountSlider.on('input', function() {
        currentAmount = parseInt($(this).val());
        amountInput.val(formatNumber(currentAmount));
    });

    amountInput.on('input', function() {
        currentAmount = parseFormattedNumber($(this).val());
        amountSlider.val(currentAmount);
    });
    
    $('.mlr-calc-control').on('click', function() {
        if (!selectedPlan) return;
        const step = 1000000;
        const action = $(this).data('action');

        if (action === 'plus') {
            currentAmount += step;
        } else {
            currentAmount -= step;
        }
        
        const min = parseInt(selectedPlan.settings.min_amount);
        const max = parseInt(selectedPlan.settings.max_amount);
        if (currentAmount < min) currentAmount = min;
        if (currentAmount > max) currentAmount = max;
        
        amountInput.val(formatNumber(currentAmount));
        amountSlider.val(currentAmount);
    });

    installmentsTabsContainer.on('click', '.installment-tab', function() {
        installmentsTabsContainer.find('.installment-tab').removeClass('active');
        $(this).addClass('active');
    });

    intervalTabsContainer.on('click', '.interval-tab', function() {
        intervalTabsContainer.find('.interval-tab').removeClass('active');
        $(this).addClass('active');
    });

    calculateBtn.on('click', function() {
        if (!selectedPlan) {
            resultDisplay.html('<p class="mlr-calc-placeholder" style="color:red;">لطفاً ابتدا یک طرح را انتخاب کنید.</p>');
            return;
        }

        const activeMonths = installmentsTabsContainer.find('.installment-tab.active').data('months');
        const interval = intervalTabsContainer.find('.interval-tab.active').data('interval') || 1;
        
        const minAmount = parseInt(selectedPlan.settings.min_amount);
        const maxAmount = parseInt(selectedPlan.settings.max_amount);
        
        if (currentAmount < minAmount || currentAmount > maxAmount) {
            resultDisplay.html(`<p class="mlr-calc-placeholder" style="color:red;">مبلغ باید بین ${formatNumber(minAmount)} و ${formatNumber(maxAmount)} ریال باشد.</p>`);
            return;
        }
        
        if (selectedPlan.type === 'leasing') {
            const minInterval = parseInt(selectedPlan.settings.min_interval);
            const maxInterval = parseInt(selectedPlan.settings.max_interval);
            if (interval < minInterval || interval > maxInterval) {
                 resultDisplay.html(`<p class="mlr-calc-placeholder" style="color:red;">فاصله اقساط باید بین ${minInterval} و ${maxInterval} ماه باشد.</p>`);
                 return;
            }
        }

        if (!activeMonths) {
            resultDisplay.html(`<p class="mlr-calc-placeholder" style="color:red;">لطفا مدت زمان بازپرداخت را انتخاب کنید.</p>`);
            return;
        }

        resultDisplay.html(`<p class="mlr-calc-placeholder">در حال محاسبه...</p>`);

        const data = {
            action: 'mlr_frontend_calculate_loan',
            nonce: mlrCalculatorAjax.nonce,
            plan_id: selectedPlan.id,
            amount: currentAmount,
            installments: activeMonths,
            installment_interval: interval,
            user_commission: mlrCalculatorAjax.user_commission,
            context: 'mlr_loan_calculator' 
        };

        $.post(mlrCalculatorAjax.ajax_url, data, function(response) {
            if (response && response.success && response.data && response.data.html) {
                 resultDisplay.html(response.data.html.replace(/<table/g, '<div class="mlr-calc-result-table"').replace(/<\/table/g, '</div').replace(/<tr/g, '<div class="mlr-calc-result-item"').replace(/<\/tr/g, '</div').replace(/<td><strong>/g, '<span class="label">').replace(/<\/strong><\/td>/g, '</span>').replace(/<td>/g, '<span class="value">').replace(/<\/td>/g, '</span>'));
            } else {
                const errorMessage = (response && response.data && response.data.message) ? response.data.message : 'خطا در محاسبه. لطفاً مقادیر را بررسی کنید.';
                resultDisplay.html('<p class="mlr-calc-placeholder" style="color:red;">' + errorMessage + '</p>');
            }
        }).fail(function() {
             resultDisplay.html('<p class="mlr-calc-placeholder" style="color:red;">خطای ارتباط با سرور. لطفاً صفحه را رفرش کنید.</p>');
        });
    });

    initializePlanTabs();
});
