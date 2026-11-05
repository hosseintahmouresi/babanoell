jQuery(document).ready(function($) {
    if ($('#mlr-loan-request-form-main').length === 0) return;

    let currentStep = 1;
    let selectedPlan = null;
    let documentsSectionPlanId = null;
    let requiresBankValidation = false;
    let loanAmountForProducts = 0;
    let creditCheckHashCode = '';
    let creditCheckInterval = null;
    let isSubmitting = false;
    let acceptorSearchTimer = null;
    const form = $('#mlr-loan-request-form-main');
    
    // --- Helper Functions ---

    function parseFormattedNumber(str) {
        if (!str) return 0;
        const persianNumbersMap = { '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9' };
        let englishStr = String(str);
        englishStr = englishStr.replace(/[۰-۹]/g, (match) => persianNumbersMap[match]);
        englishStr = englishStr.replace(/[٬,.]|\s/g, '');
        return parseInt(englishStr, 10) || 0;
    }

    function formatNumber(num) {
        if (!num || isNaN(num)) return '';
        return new Intl.NumberFormat('fa-IR').format(num);
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

    function getPlanDocumentsPayload(response) {
        const data = response && response.data ? response.data : null;
        let documents = [];
        if (Array.isArray(data)) {
            documents = data;
        } else if (data && Array.isArray(data.required_documents)) {
            documents = data.required_documents;
        } else if (data && Array.isArray(data.documents)) {
            documents = data.documents;
        } else if (response && Array.isArray(response.required_documents)) {
            documents = response.required_documents;
        }

        return documents;
    }

    function getPlanConditionsPayload(response) {
        const data = response && response.data ? response.data : null;
        if (data && !Array.isArray(data) && typeof data.plan_conditions === 'string') {
            return data.plan_conditions;
        }
        if (response && typeof response.plan_conditions === 'string') {
            return response.plan_conditions;
        }
        return '';
    }

    function hasPlanConditions(conditionsHtml) {
        const html = String(conditionsHtml || '');
        return $.trim($('<div/>').html(html).text()) !== '' || /<(img|ul|ol|li|table|br|p|div|span|strong|em|b|i)\b/i.test(html);
    }

    function getPlanInfoAccordions() {
        return form.find('.mlr-plan-info-accordions').first();
    }

    function getPlanDocsAccordion() {
        return form.find('.mlr-plan-docs-accordion').not('.mlr-plan-conditions-accordion').first();
    }

    function getPlanConditionsAccordion() {
        return form.find('.mlr-plan-conditions-accordion').first();
    }

    function setPlanDocsAccordionCollapsed(accordion, collapsed) {
        if (!accordion || accordion.length === 0) return;
        accordion.attr('data-state', collapsed ? 'collapsed' : 'expanded');
        accordion.find('.mlr-plan-docs-toggle').first().attr('aria-expanded', collapsed ? 'false' : 'true');
        accordion.find('.mlr-plan-docs-panel').first().prop('hidden', collapsed);
    }

    function hidePlanRequiredDocumentsAccordion() {
        const wrapper = getPlanInfoAccordions();
        const docsAccordion = getPlanDocsAccordion();
        const conditionsAccordion = getPlanConditionsAccordion();
        wrapper.hide();
        docsAccordion.hide();
        conditionsAccordion.hide();
        docsAccordion.find('.mlr-plan-docs-status').hide().removeClass('is-error').empty();
        docsAccordion.find('.mlr-plan-docs-list').empty();
        conditionsAccordion.find('.mlr-plan-conditions-status').hide().removeClass('is-error').empty();
        conditionsAccordion.find('.mlr-plan-conditions-content').empty();
        setPlanDocsAccordionCollapsed(docsAccordion, true);
        setPlanDocsAccordionCollapsed(conditionsAccordion, true);
    }

    function setPlanRequiredDocumentsLoading() {
        const wrapper = getPlanInfoAccordions();
        const docsAccordion = getPlanDocsAccordion();
        const conditionsAccordion = getPlanConditionsAccordion();
        wrapper.css('display', 'grid');
        docsAccordion.show();
        conditionsAccordion.hide();
        docsAccordion.find('.mlr-plan-docs-list').empty();
        docsAccordion.find('.mlr-plan-docs-status').removeClass('is-error').text('در حال دریافت مدارک و شرایط طرح...').show();
        conditionsAccordion.find('.mlr-plan-conditions-content').empty();
        setPlanDocsAccordionCollapsed(docsAccordion, true);
        setPlanDocsAccordionCollapsed(conditionsAccordion, true);
    }

    function renderPlanRequiredDocumentsAccordion(planId, docs) {
        const wrapper = getPlanInfoAccordions();
        const accordion = getPlanDocsAccordion();
        if (accordion.length === 0 || !planId) return;

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
        if (accordion.length === 0 || !planId) return;

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

    let planRequiredDocumentsRequest = null;
    function loadPlanRequiredDocuments(planId) {
        if (!planId) {
            hidePlanRequiredDocumentsAccordion();
            return;
        }

        if (planRequiredDocumentsRequest && planRequiredDocumentsRequest.readyState !== 4) {
            planRequiredDocumentsRequest.abort();
        }

        setPlanRequiredDocumentsLoading();
        const requestedPlanId = String(planId);
        planRequiredDocumentsRequest = $.post(mlr_applicant_form_ajax.ajax_url, {
            action: 'mlr_applicant_get_plan_documents',
            nonce: mlr_applicant_form_ajax.nonce,
            plan_id: planId,
            occupation: $('#occupation').val() || ''
        }).done(function(response) {
            if ($('#calc_plan_select').val() !== requestedPlanId) return;
            if (response && response.success) {
                if (Number(response.occupation_required || 0) === 1) {
                    renderPlanRequiredDocumentsAccordion(planId, []);
                    getPlanDocsAccordion().find('.mlr-plan-docs-list').html('<div class="mlr-plan-docs-empty">مدارک این طرح بر اساس شغل شما تعیین می‌شود. پس از انتخاب شغل، فهرست دقیق مدارک نمایش داده خواهد شد.</div>');
                } else {
                    renderPlanRequiredDocumentsAccordion(planId, getPlanDocumentsPayload(response));
                }
                renderPlanConditionsAccordion(planId, getPlanConditionsPayload(response));
            } else {
                const message = response && response.data && response.data.message ? response.data.message : 'خطا در دریافت مدارک مورد نیاز طرح.';
                const accordion = getPlanDocsAccordion();
                accordion.show();
                accordion.find('.mlr-plan-docs-list').empty();
                accordion.find('.mlr-plan-docs-status').addClass('is-error').text(message).show();
                setPlanDocsAccordionCollapsed(accordion, false);
            }
        }).fail(function(xhr, status) {
            if (status === 'abort') return;
            const accordion = getPlanDocsAccordion();
            accordion.show();
            accordion.find('.mlr-plan-docs-list').empty();
            accordion.find('.mlr-plan-docs-status').addClass('is-error').text('خطا در ارتباط با سرور برای دریافت مدارک مورد نیاز طرح.').show();
            setPlanDocsAccordionCollapsed(accordion, false);
        });
    }

    form.off('click.mlrPlanDocsAccordion', '.mlr-plan-docs-toggle').on('click.mlrPlanDocsAccordion', '.mlr-plan-docs-toggle', function() {
        const accordion = $(this).closest('.mlr-plan-docs-accordion');
        setPlanDocsAccordionCollapsed(accordion, $(this).attr('aria-expanded') === 'true');
    });

    const documentFlowStateAliases = {
        defect_documents: 'defect_documents',
        'نقص مدارک': 'defect_documents',
        completion_documents: 'completion_documents',
        'تکمیل مدارک': 'completion_documents',
        pending_documents: 'pending_documents',
        'در انتظار مدارک': 'pending_documents',
        'در انتظار تکمیل': 'pending_documents',
        'در انتظار تکمیل مدارک': 'pending_documents',
        initial_documents: 'initial_documents'
    };

    function normalizeDocumentFlowState(state) {
        state = String(state || '').trim();
        return documentFlowStateAliases[state] || state;
    }

    function getDocumentFlowState(context) {
        const container = context ? $(context) : form;
        const explicitState = container.data('document-flow-state') || $('input[name="document_flow_state"]').val() || '';
        return normalizeDocumentFlowState(explicitState || 'initial_documents');
    }

    function syncDocumentFlowBoxes(context) {
        const container = context ? $(context) : form;
        const state = getDocumentFlowState(container);
        const defectBox = container.find('.mlr-defect-documents-box');
        const completionBox = container.find('.mlr-completion-documents-box');

        defectBox.toggle(state === 'defect_documents')
            .find('input, select, textarea, button')
            .prop('disabled', state !== 'defect_documents');

        completionBox.toggle(state === 'completion_documents' || state === 'pending_documents')
            .find('input, select, textarea, button')
            .prop('disabled', state !== 'completion_documents' && state !== 'pending_documents');
    }

    syncDocumentFlowBoxes(form);

    // New Algorithm for Iranian National Code Validation
    function isValidIranianNationalCode(input) {
        if (!/^\d{10}$/.test(input)) return false;
        const check = parseInt(input[9]);
        const sum = input.split('').slice(0, 9).reduce((acc, x, i) => acc + parseInt(x) * (10 - i), 0) % 11;
        return (sum < 2 && check === sum) || (sum >= 2 && check === 11 - sum);
    }

    function showError(element, message) {
        element.addClass('is-invalid');
        element.closest('.mlr-form-group').find('.mlr-error-message').text(message).show();
    }

    function hideError(element) {
        element.removeClass('is-invalid');
        element.closest('.mlr-form-group').find('.mlr-error-message').hide();
    }

    // --- Dynamic Year Population (18 to 70 years old) ---
    function populateBirthYears() {
        const yearSelect = $('#birth_year');
        if (yearSelect.length === 0) return;

        // Get current Jalali Year
        const today = new Date();
        const formatter = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { year: 'numeric' });
        const currentYearString = formatter.format(today).replace(/[۰-۹]/g, d => "۰۱۲۳۴۵۶۷۸۹".indexOf(d)); // Ensure English digits
        const currentJalaliYear = parseInt(currentYearString);

        // Calculate range: 18 to 70 years ago
        const minAge = 18;
        const maxAge = 70;
        const startYear = currentJalaliYear - minAge; // e.g., 1403 - 18 = 1385
        const endYear = currentJalaliYear - maxAge;   // e.g., 1403 - 70 = 1333

        let optionsHtml = '<option value="">سال</option>';
        for (let y = startYear; y >= endYear; y--) {
            optionsHtml += `<option value="${y}">${y}</option>`;
        }
        yearSelect.html(optionsHtml);
    }

    // Initial population of years
    populateBirthYears();

    // --- Validation Logic ---

    function validateStep1() {
        let isStepValid = true;
        const nationalCode = $('#national_code_verify');
        const mobileNumber = $('#mobile_number_verify');
        const ncValue = nationalCode.val();

        if (!isValidIranianNationalCode(ncValue)) {
            showError(nationalCode, 'کد ملی وارد شده نامعتبر است.');
            isStepValid = false;
        } else {
            hideError(nationalCode);
        }

        if (!/^09\d{9}$/.test(mobileNumber.val())) {
            showError(mobileNumber, 'شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.');
            isStepValid = false;
        } else {
            hideError(mobileNumber);
        }

        $('#verify_shahkar_btn').prop('disabled', !isStepValid);
    }

    function validateStep2() {
        const planSelect = $('#calc_plan_select');
        const amountInput = $('#calc_amount');
        const intervalInput = $('#calc_interval');
        
        const amount = parseFormattedNumber(amountInput.val());
        const interval = parseInt(intervalInput.val());

        const minAmount = selectedPlan ? parseInt(selectedPlan.view_settings.min_amount) : 0;
        const maxAmount = selectedPlan ? parseInt(selectedPlan.view_settings.max_amount) : Infinity;
        const minInterval = selectedPlan ? parseInt(selectedPlan.view_settings.min_interval) : 1;
        const maxInterval = selectedPlan ? parseInt(selectedPlan.view_settings.max_interval) : 12;
        
        let isStepValid = true;

        if (!planSelect.val()) {
            showError(planSelect, 'انتخاب طرح الزامی است.');
            isStepValid = false;
        } else {
            hideError(planSelect);
        }

        if (!selectedPlan || amount < minAmount || amount > maxAmount) {
            const minText = selectedPlan ? formatNumber(minAmount) : '...';
            const maxText = selectedPlan ? formatNumber(maxAmount) : '...';
            showError(amountInput, `مبلغ باید بین ${minText} و ${maxText} باشد.`);
            isStepValid = false;
        } else {
            hideError(amountInput);
        }

        if (selectedPlan && selectedPlan.type === 'leasing') {
            if (isNaN(interval) || interval < minInterval || interval > maxInterval) {
                showError(intervalInput, `فاصله اقساط باید بین ${minInterval} و ${maxInterval} ماه باشد.`);
                isStepValid = false;
            } else {
                hideError(intervalInput);
            }
        } else {
            hideError(intervalInput);
        }

        $('#run_calculator').prop('disabled', !isStepValid);
        
        if ($('#calculation-result').is(':empty') || $('#calculation-result').hasClass('mlr-error')) {
             $('#next_step_2').prop('disabled', true);
        }
    }

    function getSelectedValues(selector) {
        const el = $(selector);
        if (el.length === 0) return [];
        const raw = el.val();
        if (Array.isArray(raw)) {
            return raw.filter(function(v) { return String(v || '').trim() !== ''; });
        }
        if (raw === null || raw === undefined || String(raw).trim() === '') {
            return [];
        }
        if (typeof raw === 'string') {
            const trimmed = raw.trim();
            if (trimmed.charAt(0) === '[') {
                try {
                    const decoded = JSON.parse(trimmed);
                    if (Array.isArray(decoded)) return decoded.filter(function(v) { return String(v || '').trim() !== ''; });
                } catch (e) {}
            }
            return trimmed.split(/[،,\n\r]+/).map(function(v) { return v.trim(); }).filter(Boolean);
        }
        return [];
    }

    function validateAcceptorAssignment() {
        if ($('.mlr-acceptor-assignment-section').length === 0) {
            return true;
        }

        const mode = $('input[name="acceptor_assignment_mode"]:checked').val() || $('input[name="acceptor_assignment_mode"][type="hidden"]').val() || '';
        let isValid = true;

        $('#acceptor-assignment-error, #selected-acceptor-error, #requested-cities-error, #requested-location-error, #requested-categories-error, #request-public-summary-error').hide();

        if (!mode) {
            $('#acceptor-assignment-error').show();
            return false;
        }

        if (mode === 'applicant_selected_acceptor') {
            if (!parseInt($('#selected_acceptor_id').val(), 10)) {
                $('#selected-acceptor-error').show();
                isValid = false;
            }
        }

        if (mode === 'open_acceptor_marketplace') {
            const scope = $('input[name="requested_service_scope"]:checked').val() || 'all_country';
            const openPanel = $('#mlr-open-marketplace-panel');
            const requireLocation = openPanel.data('require-location') !== 0 && openPanel.data('require-location') !== '0';
            const requireCategory = openPanel.data('require-category') !== 0 && openPanel.data('require-category') !== '0';
            const selectedLocations = getSelectedValues('#requested_location_terms');
            if (requireLocation && (scope === 'selected_locations' || scope === 'selected_cities') && selectedLocations.length === 0) {
                $('#requested-location-error, #requested-cities-error').show();
                isValid = false;
            }

            if (requireCategory && $('input[name="requested_activity_categories[]"]:checked').length === 0) {
                $('#requested-categories-error').show();
                isValid = false;
            }

            if (($('#request_public_summary').val() || '').trim().length < 10) {
                $('#request-public-summary-error').show();
                isValid = false;
            }
        }

        return isValid;
    }

    function validateStep4() {
        let isStepValid = true;
        
        const fullName = $('#full_name');
        if (fullName.val().trim() === '') {
            showError(fullName, 'نام و نام خانوادگی الزامی است.'); isStepValid = false;
        } else {
            hideError(fullName);
        }

        const fatherName = $('#father_name');
        if (fatherName.length > 0 && fatherName.val().trim() === '') {
            showError(fatherName, 'نام پدر الزامی است.'); isStepValid = false;
        } else {
            hideError(fatherName);
        }

        const certNum = $('#certificate_number');
        if (certNum.length > 0 && certNum.val().trim() === '') {
            showError(certNum, 'شماره شناسنامه الزامی است.'); isStepValid = false;
        } else {
            hideError(certNum);
        }

        const certCity = $('#certificate_city');
        if (certCity.length > 0 && certCity.val().trim() === '') {
            showError(certCity, 'شهر صدور شناسنامه الزامی است.'); isStepValid = false;
        } else {
            hideError(certCity);
        }

        const birthDateError = $('#birthdate-error');
        if ($('#birth_year').val() === '' || $('#birth_month').val() === '' || $('#birth_day').val() === '') {
            birthDateError.show(); isStepValid = false;
        } else {
            birthDateError.hide();
        }

        const occupation = $('#occupation');
        if (occupation.val() === '') {
            showError(occupation, 'انتخاب شغل الزامی است.'); isStepValid = false;
        } else {
            hideError(occupation);
        }

        const resAddress = $('#residential_address');
        if (resAddress.val().trim() === '') {
            showError(resAddress, 'آدرس محل سکونت الزامی است.'); isStepValid = false;
        } else {
            hideError(resAddress);
        }

        const workAddress = $('#work_address');
        if (workAddress.val().trim() === '') {
            showError(workAddress, 'آدرس محل فعالیت الزامی است.'); isStepValid = false;
        } else {
            hideError(workAddress);
        }
        
        const postalCode = $('#postal_code');
        if (!/^\d{10}$/.test(postalCode.val())) {
            showError(postalCode, 'کد پستی ۱۰ رقمی الزامی است.'); isStepValid = false;
        } else {
            hideError(postalCode);
        }

        let productTotal = 0;
        let productsValid = true;
        const productError = $('#product-error');
        if ($('.mlr-product-row').length === 0) {
            productsValid = false;
        }
        $('.mlr-product-row').each(function() {
            const name = $(this).find('.product-name').val().trim();
            const price = parseFormattedNumber($(this).find('.product-price').val());
            const qty = parseInt($(this).find('.product-qty').val());
            if (!name || isNaN(price) || price <= 0 || isNaN(qty) || qty <= 0) {
                productsValid = false;
            }
            productTotal += price * qty;
        });

        if (!productsValid || productTotal !== loanAmountForProducts) {
            productError.show(); isStepValid = false;
        } else {
            productError.hide();
        }

        if (!validateAcceptorAssignment()) {
            isStepValid = false;
        }
        
        $('#next_step_4').prop('disabled', !isStepValid);
    }

    function validateStep5() {
        var allUploaded = true;
        var anyInProgress = false;
        $('#dynamic-documents .mlr-doc-upload-group').each(function() {
            var $group = $(this);
            var urls = $group.find('input.mlr-uploaded-urls').val();
            var arr = [];
            try { arr = urls ? JSON.parse(urls) : []; } catch (e) {}
            if (arr.length === 0) allUploaded = false;
            if ($group.hasClass('mlr-upload-in-progress')) anyInProgress = true;
        });
        $('#final-submit-btn').prop('disabled', !allUploaded || anyInProgress);
    }

    function goToStep(stepNumber) {
        const $container = form.closest('.mlr-form-container');
        $container.find('.mlr-step-content').removeClass('is-active');
        $container.find('#step' + stepNumber).addClass('is-active');

        $container.find('.mlr-step-item').each(function() {
            const stepId = Number($(this).data('step-id'));
            $(this).removeClass('is-active is-completed');
            if (stepId < stepNumber) { $(this).addClass('is-completed'); }
            else if (stepId === stepNumber) { $(this).addClass('is-active'); }
        });

        // Keep the active stage visible by scrolling ONLY the stepper.
        // scrollIntoView() was intentionally avoided here because on RTL pages
        // (especially inside MihanPanel) it can horizontally scroll the entire
        // document and create a large empty gap on the left from steps 3 onward.
        if (window.matchMedia && window.matchMedia('(max-width: 1200px)').matches) {
            const stepper = $container.find('.mlr-stepper-sidebar').get(0);
            const activeStep = $container.find('.mlr-step-item.is-active:visible').get(0);

            if (stepper && activeStep) {
                window.requestAnimationFrame(function() {
                    const stepperRect = stepper.getBoundingClientRect();
                    const activeRect = activeStep.getBoundingClientRect();
                    const delta = (activeRect.left + (activeRect.width / 2))
                        - (stepperRect.left + (stepperRect.width / 2));

                    if (Math.abs(delta) > 2) {
                        if (typeof stepper.scrollBy === 'function') {
                            try {
                                stepper.scrollBy({ left: delta, behavior: 'smooth' });
                            } catch (error) {
                                stepper.scrollLeft += delta;
                            }
                        } else {
                            stepper.scrollLeft += delta;
                        }
                    }
                });
            }
        }
        currentStep = stepNumber;
        if (stepNumber === 1) validateStep1();
        if (stepNumber === 2) validateStep2();
        if (stepNumber === 4) {
            toggleAcceptorAssignmentPanels();
            toggleRequestedCities();
            validateStep4();
        }
        if (stepNumber === 5) validateStep5();
    }

    function updateDynamicDocuments(onDone) {
        if (!selectedPlan) { if (onDone) onDone(); return; }
        $('#dynamic-documents').html('<p class="mlr-loading">در حال بارگذاری مدارک مورد نیاز...</p>');
        $.post(mlr_applicant_form_ajax.ajax_url, {
            action: 'mlr_applicant_get_plan_documents', nonce: mlr_applicant_form_ajax.nonce, plan_id: selectedPlan.id, occupation: $('#occupation').val() || ''
        }, function(response) {
            var documents = getPlanDocumentsPayload(response);
            if (response.success && documents.length > 0) {
                var documentsHtml = '';
                var occupationLabel = response && response.occupation_label ? String(response.occupation_label) : '';
                if (occupationLabel) {
                    documentsHtml += '<div class="mlr-occupation-docs-notice"><strong>مدارک مورد نیاز برای شغل: ' + escapeHtml(occupationLabel) + '</strong><span>فقط مدارک همین شغل برای ثبت این درخواست الزامی است.</span></div>';
                }
                documents.forEach(function(doc) {
                    var docKey = escapeAttr(doc.key);
                    var docLabel = escapeHtml(doc.label || doc.key);
                    var requiredAttr = 'required';
                    documentsHtml += '<div class="mlr-doc-upload-group mlr-form-group" data-key="' + docKey + '" data-required="true">';
                    documentsHtml += '<label for="doc_' + docKey + '">' + docLabel + ':</label>';
                    documentsHtml += '<input type="file" id="doc_' + docKey + '" class="mlr-input mlr-doc-file-input" name="doc_file_' + docKey + '" ' + (doc.multiple ? 'multiple' : '') + ' ' + requiredAttr + ' accept=".jpg,.jpeg,.png,.pdf">';
                    documentsHtml += '<p class="mlr-upload-hint description">حداکثر حجم هر فایل ۵ مگابایت. پس از انتخاب، فایل‌ها به‌صورت خودکار آپلود می‌شوند. می‌توانید فایل‌های بیشتری اضافه یا حذف کنید.</p>';
                    documentsHtml += '<div class="mlr-uploaded-files-list" aria-live="polite"></div>';
                    documentsHtml += '<div class="mlr-upload-progress-wrap" style="display:none;"><div class="mlr-upload-progress-bar"><span class="mlr-upload-progress-fill"></span></div><span class="mlr-upload-progress-pct">0%</span></div>';
                    documentsHtml += '<div class="mlr-upload-error" role="alert" style="display:none;"></div>';
                    documentsHtml += '<input type="hidden" class="mlr-uploaded-urls" name="mlr_uploaded_doc_' + docKey + '" value="[]">';
                    documentsHtml += '</div>';
                });
                $('#dynamic-documents').html(documentsHtml);
                bindDocFileUploads();
            } else if (response && response.success && Number(response.occupation_required || 0) === 1) {
                $('#dynamic-documents').html('<p class="mlr-error">ابتدا شغل را در مرحله اطلاعات فردی انتخاب کنید تا مدارک مخصوص همان شغل نمایش داده شود.</p>');
            } else {
                $('#dynamic-documents').html('<p>برای این طرح، مدرکی جهت بارگذاری مورد نیاز نیست.</p>');
            }
            validateStep5();
            if (typeof onDone === 'function') onDone();
        }).fail(function() { $('#dynamic-documents').html('<p class="mlr-error">خطا در بارگذاری لیست مدارک.</p>'); validateStep5(); if (typeof onDone === 'function') onDone(); });
    }

    function bindDocFileUploads() {
        if (typeof MLRFileUpload === 'undefined') {
            $('#dynamic-documents .mlr-doc-file-input').on('change', function() { validateStep5(); });
            return;
        }
        $('#dynamic-documents .mlr-doc-file-input').off('change.mlrUpload').on('change.mlrUpload', function() {
            var $input = $(this);
            var $group = $input.closest('.mlr-doc-upload-group');
            var $progressWrap = $group.find('.mlr-upload-progress-wrap');
            var $progressFill = $group.find('.mlr-upload-progress-fill');
            var $pct = $group.find('.mlr-upload-progress-pct');
            var $urlsInput = $group.find('.mlr-uploaded-urls');
            var $list = $group.find('.mlr-uploaded-files-list');
            var $errorEl = $group.find('.mlr-upload-error');
            var key = $group.data('key');
            var files = $input[0].files;
            if (!files || files.length === 0) { validateStep5(); return; }

            $errorEl.hide().empty();
            $group.addClass('mlr-upload-in-progress');
            $progressWrap.show();
            var urls = [];
            try { urls = JSON.parse($urlsInput.val() || '[]'); } catch (e) {}
            var total = files.length;

            function escapeHtml(s) { if (!s) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
            function escapeAttr(s) { if (!s) return ''; return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
            function fileItemHtml(name, url) { return '<div class="mlr-uploaded-file-item" data-url="' + escapeAttr(url) + '"><span class="mlr-uploaded-file-name">' + escapeHtml(name) + '</span> <a href="#" class="mlr-view-file" title="مشاهده فایل">مشاهده</a> <button type="button" class="mlr-remove-file" title="حذف فایل">حذف</button></div>'; }

            function nextFile(index) {
                if (index >= total) {
                    $group.removeClass('mlr-upload-in-progress');
                    $progressWrap.hide();
                    $progressFill.css('width', '0%');
                    $pct.text('0%');
                    $input.val('');
                    $urlsInput.val(JSON.stringify(urls));
                    validateStep5();
                    return;
                }
                var file = files[index];
                $pct.text('0%');
                $progressFill.css('width', '0%');
                MLRFileUpload.processAndUploadFile(file, {
                    nonce: mlr_applicant_form_ajax.submit_nonce,
                    context: 'loan_request',
                    field_key: key,
                    document_flow_state: getDocumentFlowState($group),
                    ajax_url: mlr_applicant_form_ajax.ajax_url
                }, function(pct) {
                    $progressFill.css('width', pct + '%');
                    $pct.text(pct + '%');
                }).then(function(res) {
                    if (res && res.url) {
                        urls.push({url: res.url, token: res.token || ''});
                        $list.append(fileItemHtml(file.name, res.url));
                    }
                    nextFile(index + 1);
                }).catch(function(err) {
                    var errMsg = (err && err.message) ? err.message : 'خطای ناشناخته. نوع و حجم فایل را بررسی کنید.';
                    $errorEl.text('خطا در آپلود «' + escapeHtml(file.name) + '»: ' + errMsg).show();
                    nextFile(index + 1);
                });
            }
            nextFile(0);
        });
    }

    // --- Event Listeners ---

    $('#national_code_verify, #mobile_number_verify').on('keyup input', validateStep1);
    $('#calc_plan_select, #calc_amount, #calc_interval').on('change keyup input', validateStep2);
    $('#step4').on('keyup input change', 'input, select, textarea', validateStep4);
    $('#dynamic-documents').on('change', 'input[type="file"]', validateStep5);

    function toggleAcceptorAssignmentPanels() {
        const mode = $('input[name="acceptor_assignment_mode"]:checked').val() || $('input[name="acceptor_assignment_mode"][type="hidden"]').val() || '';
        $('#mlr-selected-acceptor-panel').toggle(mode === 'applicant_selected_acceptor');
        $('#mlr-open-marketplace-panel').toggle(mode === 'open_acceptor_marketplace');
        validateStep4();
    }

    function toggleRequestedCities() {
        const scope = $('input[name="requested_service_scope"]:checked').val() || 'all_country';
        $('#mlr-requested-cities-wrap, #mlr-requested-locations-wrap').toggle(scope === 'selected_cities' || scope === 'selected_locations');
        validateStep4();
    }

    function renderAcceptorSearchResults(items) {
        const box = $('#mlr-acceptor-search-results');
        if (!items || items.length === 0) {
            box.html('<div class="mlr-acceptor-result">پذیرنده‌ای با این مشخصات پیدا نشد.</div>');
            return;
        }

        let html = '';
        items.forEach(function(item) {
            html += '<div class="mlr-acceptor-result" data-id="' + parseInt(item.user_id, 10) + '" data-title="' + escapeHtml(item.title) + '">';
            html += '<div><strong>' + escapeHtml(item.title) + '</strong><br><small>' + escapeHtml(item.city || '-') + (item.categories ? ' | ' + escapeHtml(item.categories) : '') + '</small></div>';
            html += '<button type="button" class="mlr-btn mlr-btn-secondary mlr-select-acceptor-btn">انتخاب</button>';
            html += '</div>';
        });
        box.html(html);
    }

    function searchAcceptors() {
        const term = ($('#mlr-acceptor-search').val() || '').trim();
        const planId = $('#calc_plan_select').val();
        const box = $('#mlr-acceptor-search-results');

        if (!planId) {
            box.html('<div class="mlr-acceptor-result">ابتدا طرح تسهیلات را انتخاب کنید تا پذیرنده‌های مجاز نمایش داده شوند.</div>');
            return;
        }

        if (term.length < 2) {
            box.empty();
            return;
        }

        box.html('<div class="mlr-acceptor-result">در حال جستجو...</div>');
        $.post(mlr_applicant_form_ajax.ajax_url, {
            action: 'mlr_applicant_search_acceptors',
            nonce: mlr_applicant_form_ajax.acceptor_search_nonce,
            term: term,
            installment_plan_id: planId
        }).done(function(response) {
            if (response && response.success) {
                renderAcceptorSearchResults(response.data.acceptors || []);
            } else {
                const msg = response && response.data && response.data.message ? response.data.message : 'خطا در جستجوی پذیرنده.';
                box.html('<div class="mlr-acceptor-result">' + escapeHtml(msg) + '</div>');
            }
        }).fail(function() {
            box.html('<div class="mlr-acceptor-result">خطا در ارتباط با سرور.</div>');
        });
    }

    $(document).on('change', 'input[name="acceptor_assignment_mode"]', toggleAcceptorAssignmentPanels);
    $(document).on('change', 'input[name="requested_service_scope"]', toggleRequestedCities);
    $(document).on('change', '#requested_location_terms, input[name="requested_activity_categories[]"]', validateStep4);

    $(document).on('input', '#mlr-category-term-search', function() {
        const term = ($(this).val() || '').trim().toLowerCase();
        $('.mlr-category-option').each(function() {
            const haystack = String($(this).data('search') || $(this).text()).toLowerCase();
            $(this).toggle(!term || haystack.indexOf(term) !== -1);
        });
    });

    $(document).on('input', '#mlr-location-term-search', function() {
        const term = ($(this).val() || '').trim().toLowerCase();
        $('#requested_location_terms option').each(function() {
            const haystack = String($(this).data('search') || $(this).text()).toLowerCase();
            const matched = !term || haystack.indexOf(term) !== -1;
            $(this).prop('hidden', !matched).toggle(matched);
        });
    });

    $('#occupation').on('change', function() {
        documentsSectionPlanId = null;
        $('#dynamic-documents').empty();
        if (selectedPlan) {
            loadPlanRequiredDocuments(selectedPlan.id);
        }
    });

    $('#calc_plan_select').on('change', function() {
        $('#selected_acceptor_id').val('');
        $('#mlr-selected-acceptor-card').hide().empty();
        $('#mlr-acceptor-search-results').empty();
        validateStep4();
    });
    $('#mlr-acceptor-search').on('input', function() {
        $('#selected_acceptor_id').val('');
        $('#mlr-selected-acceptor-card').hide().empty();
        clearTimeout(acceptorSearchTimer);
        acceptorSearchTimer = setTimeout(searchAcceptors, 350);
        validateStep4();
    });
    $('#mlr-acceptor-search-results').on('click', '.mlr-select-acceptor-btn', function() {
        const row = $(this).closest('.mlr-acceptor-result');
        const id = parseInt(row.data('id'), 10);
        const title = row.data('title') || '';
        if (!id) return;
        $('#selected_acceptor_id').val(id);
        $('#mlr-selected-acceptor-card').html('<div><strong>پذیرنده انتخاب‌شده:</strong> ' + escapeHtml(title) + '</div><button type="button" class="mlr-btn mlr-btn-secondary" id="mlr-change-acceptor-btn">تغییر</button>').show();
        $('#mlr-acceptor-search-results').empty();
        validateStep4();
    });
    $(document).on('click', '#mlr-change-acceptor-btn', function() {
        $('#selected_acceptor_id').val('');
        $('#mlr-selected-acceptor-card').hide().empty();
        $('#mlr-acceptor-search').focus();
        validateStep4();
    });

    $('#mlr-loan-request-form-main').on('click', '.mlr-view-file', function(e) {
        e.preventDefault();
        var url = $(this).closest('.mlr-uploaded-file-item').data('url');
        if (url) window.open(url, '_blank', 'noopener,noreferrer');
    });
    $('#mlr-loan-request-form-main').on('click', '.mlr-remove-file', function(e) {
        e.preventDefault();
        var $item = $(this).closest('.mlr-uploaded-file-item');
        var urlToRemove = $item.data('url');
        var $group = $item.closest('.mlr-doc-upload-group');
        var $hidden = $group.find('input.mlr-uploaded-urls');
        var urls = [];
        try { urls = JSON.parse($hidden.val() || '[]'); } catch (err) { urls = []; }
        urls = urls.filter(function(u) { return (typeof u === 'string' ? u : (u && u.url)) !== urlToRemove; });
        $hidden.val(JSON.stringify(urls));
        $item.remove();
        validateStep5();
    });

    $('#verify_shahkar_btn').on('click', function() {
        const btn = $(this), msgDiv = $('#mlr-message');
        
        const nc = $('#national_code_verify').val();
        if(!isValidIranianNationalCode(nc)) {
             showError($('#national_code_verify'), 'کد ملی نامعتبر است.');
             return;
        }

        btn.prop('disabled', true).text('در حال بررسی...');
        msgDiv.html('درحال ارسال اطلاعات...').removeClass().addClass('mlr-loading').fadeIn();
        $.post(mlr_applicant_form_ajax.ajax_url, {
            action: 'mlr_verify_shahkar', nonce: mlr_applicant_form_ajax.nonce, national_code: nc, mobile_number: $('#mobile_number_verify').val()
        }).done(function(res) {
            if (res.success) {
                msgDiv.html('✅ ' + res.data.message).removeClass().addClass('mlr-success');
                $('#national_code').val($('#national_code_verify').val());
                $('#mobile_number').val($('#mobile_number_verify').val());
                setTimeout(() => { goToStep(2); msgDiv.fadeOut().empty(); }, 1500);
            } else {
                msgDiv.html('❌ ' + (res.data.message || 'خطا')).removeClass().addClass('mlr-error');
                btn.prop('disabled', false).text('بررسی تطابق و ادامه');
            }
        }).fail(() => { msgDiv.html('خطای سرور.').removeClass().addClass('mlr-error'); btn.prop('disabled', false).text('بررسی تطابق و ادامه'); });
    });
    
    $('#calc_plan_select').on('change', function() {
        const selectedPlanId = $(this).val();
        documentsSectionPlanId = null;
        $('#calculation-result').empty().hide();
        $('#next_step_2').prop('disabled', true);
        const amountInput = $('#calc_amount');
        const intervalInput = $('#calc_interval');
        if (!selectedPlanId) {
            $('#calc_installments').html(''); selectedPlan = null; hidePlanRequiredDocumentsAccordion(); validateStep2(); return;
        }
        selectedPlan = mlr_applicant_form_ajax.plans.find(p => p.id == selectedPlanId);
        if (!selectedPlan) { hidePlanRequiredDocumentsAccordion(); return; }
        loadPlanRequiredDocuments(selectedPlan.id);
        const viewSettings = selectedPlan.view_settings;
        requiresBankValidation = viewSettings.requires_bank_validation == 1;
        $('#step-indicator-3').toggle(requiresBankValidation);
        if (viewSettings.active_installments) {
            const installmentsOptions = viewSettings.active_installments.split(',');
            let optionsHtml = '';
            installmentsOptions.forEach(function(inst) { optionsHtml += `<option value="${inst.trim()}">${inst.trim()} ماه</option>`; });
            $('#calc_installments').html(optionsHtml);
        }
        if (viewSettings.min_amount && viewSettings.max_amount) {
            amountInput.attr({'min': viewSettings.min_amount, 'max': viewSettings.max_amount, 'placeholder': `بین ${formatNumber(viewSettings.min_amount)} تا ${formatNumber(viewSettings.max_amount)} ریال`});
        }
        amountInput.off('keyup.formatter').on('keyup.formatter', function(e) {
            if (['ArrowLeft', 'ArrowRight', 'Backspace', 'Delete', 'Tab'].includes(e.key)) return;
            let rawValue = parseFormattedNumber($(this).val());
            $(this).val(formatNumber(rawValue));
        });
        if (selectedPlan.type === 'leasing') {
            $('#calc_interval_wrapper').slideDown(); 
            $('#calc_amount_label').text('مبلغ خرید (ریال):');
            if (viewSettings.min_interval && viewSettings.max_interval) {
                intervalInput.attr({ 'min': viewSettings.min_interval, 'max': viewSettings.max_interval });
                intervalInput.val(viewSettings.min_interval);
            }
        } else {
            $('#calc_interval_wrapper').slideUp(); 
            $('#calc_amount_label').text('مبلغ تسهیلات (ریال):');
        }
        validateStep2();
    });

    $('#run_calculator').on('click', function() {
        const resultDiv = $('#calculation-result');
        const data = { action: 'mlr_applicant_frontend_calculate_loan', nonce: mlr_applicant_form_ajax.calc_nonce, plan_id: selectedPlan.id, amount: parseFormattedNumber($('#calc_amount').val()), installments: $('#calc_installments').val(), installment_interval: $('#calc_interval').val(), user_commission: mlr_applicant_form_ajax.user_commission, context: mlr_applicant_form_ajax.context || 'mlr_applicant_loan_request_form' };
        resultDiv.html('<p class="mlr-loading">در حال محاسبه...</p>').slideDown();
        $('#next_step_2').prop('disabled', true);
        $.post(mlr_applicant_form_ajax.ajax_url, data, function(response) {
            if (response.success) {
                resultDiv.html(response.data.html).removeClass('mlr-error');
                $('#next_step_2').prop('disabled', false);
                if (response.data.results) {
                    $('#guarantee_check_principal').val(response.data.results.guarantee_check_principal || 0);
                    $('#guarantee_check_total').val(response.data.results.guarantee_check_total || 0);
                    $('#prepayment').val(response.data.results.prepayment || 0);
                }
            } else {
                resultDiv.html('<p class="mlr-error">' + (response.data.message || 'خطای ناشناخته') + '</p>').addClass('mlr-error');
            }
        });
    });

    // --- Credit Validation Logic (Step 3) ---
    $('#mlr-start-credit-check-btn').on('click', function() {
        const btn = $(this);
        const nc = $('#national_code').val();
        const mob = $('#mobile_number').val();

        if (!nc || !mob) {
            alert('اطلاعات هویتی یافت نشد. لطفاً مرحله ۱ را مجدداً انجام دهید.');
            goToStep(1);
            return;
        }

        $('#credit_report_json').val('');
        $('#credit_proof_token').val('');
        btn.prop('disabled', true).text('در حال ارسال...');
        
        $.ajax({
            url: mlr_applicant_form_ajax.ajax_url + '?action=mlr_credit_request_otp', // Force action in URL to avoid 400 error
            type: 'POST',
            data: {
                action: 'mlr_credit_request_otp', 
                nonce: mlr_applicant_form_ajax.nonce,
                national_code: nc, 
                mobile: mob
            },
            success: function(res) {
            if (res.success) {
                creditCheckHashCode = res.data.hash_code;
                $('#mlr-credit-check-start').slideUp();
                $('#mlr-credit-check-otp').find('.mlr-step-description').text((res.data && res.data.message) ? res.data.message + ' کد دریافتی را وارد کنید.' : 'کد تایید پیامک شده به شماره موبایل خود را وارد کنید.');
                $('#mlr-credit-check-otp').slideDown();
            } else {
                alert(res.data.message || 'خطا در ارسال درخواست.');
                btn.prop('disabled', false).text('ارسال کد اعتبارسنجی');
            }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error, xhr && xhr.responseText ? xhr.responseText : '');
                alert('خطای ارتباط با سرور (کد ' + xhr.status + '). لطفاً مجدداً تلاش کنید.');
                btn.prop('disabled', false).text('ارسال کد اعتبارسنجی');
            }
        });
    });

    $('#mlr-verify-credit-otp-btn').on('click', function() {
        const otp = ($('#mlr_credit_otp_input').val() || '').replace(/[^0-9۰-۹٠-٩]/g, '');
        if (!otp) { alert('لطفاً کد تایید را وارد کنید.'); return; }
        const btn = $(this);
        btn.prop('disabled', true).text('در حال بررسی...');
        
        $.post(mlr_applicant_form_ajax.ajax_url, {
            action: 'mlr_credit_verify_otp', nonce: mlr_applicant_form_ajax.nonce, hash_code: creditCheckHashCode, otp: otp
        }).done(function(res) {
            if (res.success) {
                $('#mlr-credit-check-otp').slideUp();
                $('#mlr-credit-loading-text').text((res.data && res.data.message) ? res.data.message : 'در حال بررسی وضعیت...');
                $('#mlr-credit-check-loading').slideDown();
                startCreditStatusPolling();
            } else {
                alert(res.data.message || 'کد اشتباه است.');
                btn.prop('disabled', false).text('تایید و دریافت گزارش');
            }
        }).fail(() => { alert('خطای سرور.'); btn.prop('disabled', false).text('تایید و دریافت گزارش'); });
    });

    function startCreditStatusPolling() {
        let attempts = 0;
        
        function poll() {
            attempts++;
            // افزایش تعداد تلاش‌ها به ۶۰ (حدود ۵ دقیقه)
            if (attempts > 60) {
                $('#mlr-credit-check-loading').html('<p class="mlr-error">زمان انتظار به پایان رسید. لطفاً مجدداً تلاش کنید.</p>');
                $('#mlr-start-credit-check-btn').prop('disabled', false).text('تلاش مجدد');
                return;
            }
            
            $.post(mlr_applicant_form_ajax.ajax_url, {
                action: 'mlr_credit_check_status', nonce: mlr_applicant_form_ajax.nonce, hash_code: creditCheckHashCode
            }).done(function(res) {
                console.log('Credit Check Status (' + attempts + '):', res); // لاگ دقیق‌تر
                if (res.success) {
                    if (res.data.status === 'ready') {
                        const proofToken = (res.data && res.data.proof_token) ? String(res.data.proof_token) : '';
                        $('#credit_proof_token').val(proofToken);
                        $('#mlr-credit-check-loading').slideUp();
                        renderCreditReport(res.data.data);
                        $('#mlr-credit-check-result').slideDown();
                    } else {
                        // نمایش پیام وضعیت فعلی (مثلاً: احراز هویت انجام شد)
                        let msg = res.data.message || 'در حال پردازش...';
                        if (res.data.api_status) {
                            console.log('API Status:', res.data.api_status);
                        }
                        $('#mlr-credit-loading-text').text(msg + ' (لطفاً شکیبا باشید...)');
                        // افزایش فاصله زمانی به ۵ ثانیه
                        setTimeout(poll, 5000); 
                    }
                } else {
                    // Handle error status (e.g. ReportGenerationFailed)
                    $('#mlr-credit-check-loading').hide();
                    $('#mlr-credit-check-start').slideDown();
                    $('#mlr-start-credit-check-btn').prop('disabled', false).text('تلاش مجدد');
                    alert(res.data.message || 'خطا در استعلام وضعیت.');
                }
            }).fail(function(xhr, status, error) {
                console.error('Polling Error:', status, error, xhr && xhr.responseText ? xhr.responseText : '');
                $('#mlr-credit-check-loading').hide();
                $('#mlr-credit-check-start').slideDown();
                $('#mlr-start-credit-check-btn').prop('disabled', false).text('تلاش مجدد');
                alert('خطای ارتباط با سرور در دریافت وضعیت.');
            });
        }

        poll(); // Start polling
    }

    function renderCreditReport(data) {
        const fmt = (num) => new Intl.NumberFormat('fa-IR').format(num || 0);
        let html = '<div class="mlr-credit-report">';
        
        // 1. Score Section (Graphical Gauge)
        const scoreObj = data.score || {};
        const reportObj = data.report || {};
        const score = scoreObj.score !== undefined ? scoreObj.score : 0;
        const risk = scoreObj.risk || 'نامشخص';
        const desc = scoreObj.description || '';
        const minScore = scoreObj.minLimit || 0;
        const maxScore = scoreObj.maxLimit || 1000;
        
        // Calculate percentage for gauge
        let percent = 0;
        if (maxScore > minScore) {
             percent = ((score - minScore) / (maxScore - minScore)) * 100;
        }
        percent = Math.max(0, Math.min(100, percent));
        const strokeDashArray = `${percent}, 100`;
        
        let riskClass = 'mlr-risk-unknown';
        if (risk.includes('کم') || risk.includes('Low') || ['A1','A2','A3'].includes(risk)) riskClass = 'mlr-risk-low';
        else if (risk.includes('متوسط') || risk.includes('Medium') || ['B1','B2','B3'].includes(risk)) riskClass = 'mlr-risk-medium';
        else riskClass = 'mlr-risk-high';

        html += `
            <div class="mlr-report-header ${riskClass}">
                <div class="mlr-score-circle-wrapper">
                    <svg viewBox="0 0 36 36" class="mlr-circular-chart">
                        <path class="mlr-circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="mlr-circle" stroke-dasharray="${strokeDashArray}" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <text x="18" y="20.35" class="mlr-percentage">${score}</text>
                    </svg>
                    <span class="mlr-score-label">امتیاز اعتباری</span>
                </div>
                <div class="mlr-risk-box">
                    <div class="mlr-risk-title">ریسک: ${risk}</div>
                    <div class="mlr-risk-desc">${desc}</div>
                </div>
            </div>
        `;

        // 2. Personal Info
        const pInfo = scoreObj.personInformation || reportObj.personInformation || {};
        if (Object.keys(pInfo).length > 0) {
            html += `
                <div class="mlr-report-section">
                    <h4><span class="dashicons dashicons-id-alt"></span> اطلاعات هویتی</h4>
                    <div class="mlr-report-grid">
                        <div><span class="mlr-label">نام و نام خانوادگی:</span> <strong>${pInfo.name || pInfo.firstName || ''} ${pInfo.family || pInfo.lastName || ''}</strong></div>
                        <div><span class="mlr-label">کد ملی:</span> <strong>${pInfo.nationalCode || '-'}</strong></div>
                        <div><span class="mlr-label">نام پدر:</span> <strong>${pInfo.fatherName || '-'}</strong></div>
                        <div><span class="mlr-label">تاریخ تولد:</span> <strong>${pInfo.dateOfBirth || pInfo.birthDate || '-'}</strong></div>
                    </div>
                </div>
            `;
        }

        // 3. Summary Dashboard (New)
        const summary = (reportObj.summaryInformationRelatedToContractsSum && reportObj.summaryInformationRelatedToContractsSum[0]) ? reportObj.summaryInformationRelatedToContractsSum[0] : {};
        const bouncedObj = data.bouncedCheque || {};
        const bouncedCount = bouncedObj.bouncedCheques ? bouncedObj.bouncedCheques.length : 0;
        
        html += `
            <div class="mlr-report-section">
                <h4><span class="dashicons dashicons-chart-bar"></span> خلاصه وضعیت مالی</h4>
                <div class="mlr-summary-grid">
                    <div class="mlr-summary-card">
                        <span class="mlr-summary-icon dashicons dashicons-media-document"></span>
                        <span class="mlr-summary-val">${summary.inProgressContractsCount || 0}</span>
                        <span class="mlr-summary-label">تسهیلات جاری</span>
                    </div>
                    <div class="mlr-summary-card">
                        <span class="mlr-summary-icon dashicons dashicons-money-alt"></span>
                        <span class="mlr-summary-val">${fmt(summary.overdueAmount)}</span>
                        <span class="mlr-summary-label">معوقات (ریال)</span>
                    </div>
                    <div class="mlr-summary-card">
                        <span class="mlr-summary-icon dashicons dashicons-warning"></span>
                        <span class="mlr-summary-val">${bouncedCount}</span>
                        <span class="mlr-summary-label">چک برگشتی</span>
                    </div>
                    <div class="mlr-summary-card">
                        <span class="mlr-summary-icon dashicons dashicons-portfolio"></span>
                        <span class="mlr-summary-val">${fmt(summary.totalAmount)}</span>
                        <span class="mlr-summary-label">کل تسهیلات (ریال)</span>
                    </div>
                </div>
            </div>
        `;

        // 4. Direct Contracts (Loans)
        const loans = reportObj.directContracts || [];
        if (loans.length > 0) {
            html += `<div class="mlr-report-section"><h4><span class="dashicons dashicons-bank"></span> تسهیلات بانکی (وام‌ها)</h4><div class="mlr-cards-container">`;
            loans.forEach(loan => {
                const statusText = loan.negativeContractStatus || 'جاری';
                const statusClass = (statusText.includes('معوق') || statusText.includes('مشکوک')) ? 'mlr-badge-danger' : (statusText.includes('گذشته') ? 'mlr-badge-warning' : 'mlr-badge-success');
                
                html += `
                <div class="mlr-contract-card">
                    <div class="mlr-contract-header">
                        <span class="mlr-contract-org">${loan.organizationName || 'بانک'}</span>
                        <span class="mlr-contract-badge ${statusClass}">${statusText}</span>
                    </div>
                    <div class="mlr-contract-body">
                        <div class="mlr-contract-row"><span>نوع:</span> <strong>${loan.typeOfFinancingTitle || '-'}</strong></div>
                        <div class="mlr-contract-row"><span>مبلغ کل:</span> <strong>${fmt(loan.totalAmount)} ریال</strong></div>
                        <div class="mlr-contract-row"><span>مانده بدهی:</span> <strong>${fmt(loan.overdueAmount)} ریال</strong></div>
                        <div class="mlr-contract-row"><span>شروع:</span> <strong>${loan.startDate || '-'}</strong></div>
                        <div class="mlr-contract-row"><span>پایان:</span> <strong>${loan.expectedEndDate || '-'}</strong></div>
                    </div>
                </div>`;
            });
            html += `</div></div>`;
        }

        // 5. Indirect Contracts (Guarantees)
        const guarantees = reportObj.indirectContracts || [];
        if (guarantees.length > 0) {
            html += `<div class="mlr-report-section"><h4><span class="dashicons dashicons-groups"></span> تعهدات (ضمانت‌ها)</h4><div class="mlr-cards-container">`;
            guarantees.forEach(g => {
                const statusText = g.negativeContractStatus || 'جاری';
                const statusClass = (statusText.includes('معوق')) ? 'mlr-badge-danger' : 'mlr-badge-success';
                html += `
                <div class="mlr-contract-card">
                    <div class="mlr-contract-header">
                        <span class="mlr-contract-org">${g.organizationName || 'بانک'} (ضامن)</span>
                        <span class="mlr-contract-badge ${statusClass}">${statusText}</span>
                    </div>
                    <div class="mlr-contract-body">
                        <div class="mlr-contract-row"><span>مبلغ ضمانت:</span> <strong>${fmt(g.totalAmount)} ریال</strong></div>
                        <div class="mlr-contract-row"><span>معوقات وام‌گیرنده:</span> <strong>${fmt(g.overdueAmount)} ریال</strong></div>
                        <div class="mlr-contract-row"><span>سررسید:</span> <strong>${g.expectedEndDate || '-'}</strong></div>
                    </div>
                </div>`;
            });
            html += `</div></div>`;
        }

        // 6. Pichak (Cheque Summary)
        const pichak = data.pichak && data.pichak.personChequesData ? data.pichak.personChequesData.personChequeItems : [];
        if (pichak.length > 0) {
            html += `<div class="mlr-report-section"><h4><span class="dashicons dashicons-tickets-alt"></span> وضعیت چک‌های صیادی (پیچک)</h4>
            <div class="mlr-table-wrapper"><table class="mlr-report-table">
                <thead><tr><th>وضعیت</th><th>تعداد</th><th>مبلغ کل (ریال)</th></tr></thead>
                <tbody>`;
            pichak.forEach(item => {
                html += `<tr><td>${item.checkStatusTitle}</td><td>${item.count}</td><td>${fmt(item.totalAmount)}</td></tr>`;
            });
            html += `</tbody></table></div></div>`;
        }

        // 7. Bounced Cheques (Detailed Table)
        const chequeItems = bouncedObj.bouncedCheques || [];
        
        html += `<div class="mlr-report-section"><h4><span class="dashicons dashicons-warning"></span> سوابق چک برگشتی</h4>`;
        if (chequeItems.length > 0) {
             html += `<div class="mlr-table-wrapper"><table class="mlr-report-table">
                <thead><tr><th>بانک</th><th>مبلغ (ریال)</th><th>تاریخ برگشت</th><th>تاریخ رفع سوءاثر</th></tr></thead>
                <tbody>`;
             chequeItems.forEach(item => {
                 const cleared = item.clearedDate ? item.clearedDate.replace('T', ' ').split(' ')[0] : '---';
                 const bouncedDate = item.bouncedDate ? item.bouncedDate.replace('T', ' ').split(' ')[0] : '-';
                 html += `<tr>
                    <td>${item.organizationName || '-'}</td>
                    <td>${fmt(item.amount)}</td>
                    <td>${bouncedDate}</td>
                    <td>${cleared}</td>
                 </tr>`;
             });
             html += `</tbody></table></div>`;
        } else {
            html += `<div class="mlr-status-item status-success">✅ فاقد چک برگشتی</div>`;
        }
        html += `</div>`;

        // 8. Tax (Detailed Table)
        const tax = data.tax || {};
        const taxItems = tax.taxResult || [];

        html += `<div class="mlr-report-section"><h4><span class="dashicons dashicons-money"></span> بدهی مالیاتی</h4>`;
        if (taxItems.length > 0) {
             html += `<div class="mlr-table-wrapper"><table class="mlr-report-table">
                <thead><tr><th>سال</th><th>درآمد ابرازی</th><th>مالیات قطعی</th><th>بدهی</th></tr></thead>
                <tbody>`;
             let hasTax = false;
             taxItems.forEach(item => {
                 if(item.year) {
                     hasTax = true;
                     html += `<tr><td>${item.year}</td><td>${fmt(item.daramadEbrazi)}</td><td>${fmt(item.maliatGhati)}</td><td>${fmt(item.bedehiMaliatAmalkard)}</td></tr>`;
                 }
             });
             html += `</tbody></table></div>`;
             if(!hasTax) html += `<div class="mlr-status-item status-success">✅ فاقد اطلاعات مالیاتی</div>`;
        } else {
            html += `<div class="mlr-status-item status-success">✅ فاقد بدهی مالیاتی</div>`;
        }
        html += `</div>`;
        
        // 9. Judiciary
        const jud = data.judiciary && data.judiciary.judiciaryData ? data.judiciary.judiciaryData : {};
        if (jud.sumExecuteCount > 0 || jud.sumNotExecuteCount > 0) {
             html += `<div class="mlr-report-section"><h4><span class="dashicons dashicons-gavel"></span> پرونده‌های قضایی</h4>
             <div class="mlr-report-grid">
                <div><span>پرونده‌های اجرا شده:</span> <strong>${jud.sumExecuteCount}</strong></div>
                <div><span>پرونده‌های اجرا نشده:</span> <strong>${jud.sumNotExecuteCount}</strong></div>
                <div><span>محکومیت اجرا نشده:</span> <strong>${fmt(jud.sumNotExecuteSumPenaltyAmount)} ریال</strong></div>
             </div></div>`;
        }

        // Save full report JSON to hidden input for submission
        $('#credit_report_json').val(JSON.stringify(data));

        html += '</div>'; // End .mlr-credit-report
        $('#mlr-credit-score-display').html(html);
    }

    $('#birth_day, #birth_month, #birth_year').on('change', function() {
        const year = $('#birth_year').val(); const month = $('#birth_month').val(); const day = $('#birth_day').val();
        if (year && month && day) {
            // Store as jalali string (backend should handle conversion if needed or store as is)
            $('input[name="birth_date"]').val(`${year}/${String(month).padStart(2, '0')}/${String(day).padStart(2, '0')}`);
        } else {
            $('input[name="birth_date"]').val('');
        }
        validateStep4();
    });

    function updateProductTotal() {
        let grandTotal = 0;
        $('.mlr-product-row').each(function() {
            const price = parseFormattedNumber($(this).find('.product-price').val());
            const qty = parseInt($(this).find('.product-qty').val()) || 0;
            grandTotal += price * qty;
            $(this).find('.product-row-total').text(formatNumber(price * qty) + ' ریال');
        });
        $('#order_total').val(grandTotal);
        $('#display_product_total').text(formatNumber(grandTotal));
        const summaryDiv = $('.mlr-product-total-summary');
        summaryDiv.removeClass('match mismatch');
        if (grandTotal === loanAmountForProducts && grandTotal > 0) {
            summaryDiv.addClass('match');
        } else {
            summaryDiv.addClass('mismatch');
        }
        validateStep4();
    }

    $('#mlr-product-repeater').on('keyup input change', '.product-price, .product-qty', updateProductTotal);
    $('#mlr-product-repeater').on('blur', '.product-price', function() { $(this).val(formatNumber(parseFormattedNumber($(this).val()))); });
    $('#mlr-add-product-btn').on('click', () => {
        const newRow = $('.mlr-product-row:first').clone();
        newRow.find('input').val(''); newRow.find('.product-qty').val('1'); newRow.find('.product-row-total').text('0 ریال'); newRow.find('.mlr-remove-product-btn').show();
        $('#mlr-product-repeater').append(newRow); updateProductTotal();
    });
    $('#mlr-product-repeater').on('click', '.mlr-remove-product-btn', function() {
        if ($('.mlr-product-row').length > 1) { $(this).closest('.mlr-product-row').remove(); updateProductTotal(); }
    });

    $('.mlr-next-btn').on('click', function() {
        if ($(this).is(':disabled')) return;
        const targetStep = $(this).data('target-step');
        if ($(this).attr('id') === 'next_step_2') {
            loanAmountForProducts = parseFormattedNumber($('#calc_amount').val());
            $('#display_loan_amount').text(formatNumber(loanAmountForProducts));
            updateProductTotal();
            goToStep(requiresBankValidation ? 3 : 4);
        } else if (targetStep === 5) {
            let productData = [];
            $('.mlr-product-row').each(function() { productData.push({ id: $(this).find('.product-id').val().trim(), name: $(this).find('.product-name').val().trim(), price: parseFormattedNumber($(this).find('.product-price').val()), qty: parseInt($(this).find('.product-qty').val()) }); });
            $('#order_items_json').val(JSON.stringify(productData));
            if (documentsSectionPlanId === selectedPlan.id) {
                goToStep(5);
            } else {
                updateDynamicDocuments(function() {
                    documentsSectionPlanId = selectedPlan.id;
                    goToStep(5);
                });
            }
        } else {
            goToStep(targetStep);
        }
    });
    
    $('.mlr-prev-btn').on('click', function() {
        let targetStep = $(this).data('target-step');
        if (currentStep === 4 && !requiresBankValidation) {
            goToStep(2);
        } else {
            goToStep(targetStep);
        }
    });

    $('#final-submit-btn').on('click', function() {
        const btn = $(this);
        const feedbackDiv = $('#mlr-submission-feedback');
        if (isSubmitting) {
            return;
        }

        if (!validateAcceptorAssignment()) {
            goToStep(4);
            feedbackDiv.html('<div id="mlr-message" class="mlr-error" style="display:block;">لطفاً وضعیت پذیرنده و شرایط تأمین را کامل کنید.</div>');
            return;
        }

        validateStep5();
        if (btn.is(':disabled')) {
            feedbackDiv.html('<div id="mlr-message" class="mlr-error" style="display:block;">لطفا تمام مدارک الزامی را بارگذاری نمایید و تا پایان آپلود هر فایل صبر کنید.</div>');
            return;
        }

        isSubmitting = true;
        btn.prop('disabled', true).text('در حال ثبت درخواست...');
        feedbackDiv.html('<div id="mlr-message" class="mlr-loading" style="display:block;">لطفاً صبر کنید...</div>');

        var formData = new FormData();
        var $form = form;
        $.each($form.serializeArray(), function(i, field) {
            if (field.name)
                formData.append(field.name, field.value);
        });
        formData.set('action', 'mlr_applicant_submit_loan_request');
        formData.set('_wpnonce', mlr_applicant_form_ajax.submit_nonce);

        $.ajax({
            url: mlr_applicant_form_ajax.ajax_url, type: 'POST', data: formData, processData: false, contentType: false,
        }).done(function(response) {
            if (response.success) {
                var requestId = response.data && response.data.request_id ? response.data.request_id : '';
                var successText = response.data && response.data.message ? response.data.message : ('درخواست شما با موفقیت ثبت شد.' + (requestId ? ' کد پیگیری: #' + requestId : ''));
                var successBox = $('<div/>', {
                    id: 'mlr-message',
                    class: 'mlr-success mlr-submission-success',
                    css: {
                        display: 'block',
                        padding: '18px 20px',
                        margin: '0 0 20px',
                        borderRadius: '10px',
                        fontSize: '16px',
                        fontWeight: '700',
                        lineHeight: '1.9'
                    }
                }).text(successText);
                feedbackDiv.empty().append(successBox);
                form.find('input, select, textarea, button').prop('disabled', true);
                btn.prop('disabled', true).text('درخواست ثبت شد');
                if (feedbackDiv.length && feedbackDiv[0].scrollIntoView) {
                    feedbackDiv[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else if (feedbackDiv.length) {
                    window.scrollTo({ top: Math.max(0, feedbackDiv.offset().top - 120), behavior: 'smooth' });
                }
                var redirectUrl = response.data && response.data.redirect_url ? response.data.redirect_url : window.location.href;
                setTimeout(function() { window.location.href = redirectUrl; }, 4000);
            } else {
                var errorMessage = response.data && response.data.message ? response.data.message : 'خطای ناشناخته رخ داد.';
                feedbackDiv.html('<div id="mlr-message" class="mlr-error" style="display:block;">' + errorMessage + '</div>');
                isSubmitting = false;
                btn.prop('disabled', false).text('ارسال نهایی درخواست');
            }
        }).fail(function() {
            feedbackDiv.html('<div id="mlr-message" class="mlr-error" style="display:block;">خطا در ارتباط با سرور.</div>');
            isSubmitting = false;
            btn.prop('disabled', false).text('ارسال نهایی درخواست');
        });
    });
    
    goToStep(1);
});
