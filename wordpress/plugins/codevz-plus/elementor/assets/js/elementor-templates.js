jQuery( function($) {
    let tabInitialized = false;
    let codevzTemplatesCache = null;

	function loadCodevzTemplates() {
	    const modal = jQuery('.dialog-widget.elementor-templates-modal:visible');
	    const loader = modal.find('.dialog-loading');
	    const content = jQuery('#codevz-plus-content');

        if (codevzTemplatesCache) {
            content.html(codevzTemplatesCache).show();
            return;
        }

	    content.html('');
	    loader.show();

	    jQuery.ajax({
	        url: codevzData.ajaxurl,
	        method: 'POST',
	        data: {
	            action: 'codevz_get_templates',
	            security: codevzData.nonce
	        },
	        success: function(res) {
	            loader.hide();
	            if (res.success && res.data.length) {

                    const allCount = res.data.length;

                    let html = `<div id="elementor-template-library-templates" class="no-bulk-selections" data-template-source="remote" style="display: block">
                        <div id="codevz-template-library-toolbar" style="display:flex;justify-content:space-between;align-items:center;">
                            <div class="codevz-filters">
                                <a href="#" class="codevz-active">${codevzData.all} (${allCount})</a>
                                <a href="#">${codevzData.light}</a>
                                <a href="#">${codevzData.dark}</a>
                                <a href="#">${codevzData.hero}</a>
                                <a href="#">${codevzData.gallery}</a>
                                <a href="#">${codevzData.blog}</a>
                                <a href="#">${codevzData.shop}</a>
                                <a href="#">${codevzData.service}</a>
                                <a href="#">${codevzData.about}</a>
                                <a href="#">${codevzData.faq}</a>
                                <a href="#">${codevzData.slide}</a>
                                <a href="#">${codevzData.form}</a>
                            </div>
                            <div class="codevz-search-templates">
                                <input type="search" placeholder="Search templates..." style="width:200px;border: none; border-block-end: var(--e-a-border-bold); border-radius: 0; font-size: 12px; padding: 8px 15px 8px 0; transition: border .5s; background: none;">
                                <i class="eicon-search"></i>
                            </div>
                        </div>

                        <div id="elementor-template-library-templates-container" style="margin:0;padding:30px 0 50px;column-count:3;column-gap:30px;display:block">
                    `;

	                res.data.forEach(item => {
	                    html += `
                            <div class="elementor-template-library-template elementor-template-library-template-remote elementor-template-library-template-block" style="width:auto;margin:0 0 30px">
                                <div class="elementor-template-library-template-body">
                                    <img src="${item.thumb}" loading="lazy">
                                </div>
                                <div class="elementor-template-library-template-footer">
                                    <a class="codevz-insert-btn elementor-template-library-template-action elementor-template-library-template-insert elementor-button e-primary" data-id="${item.id}">
                                        <i class="eicon-library-download" aria-hidden="true"></i>
                                        <span class="elementor-button-title">Insert</span>
                                    </a>

                                        <div class="elementor-template-library-template-name">Template ${item.id}</div>
                                        <div class="codevz-elementor-templates-keywords" style="display:none">${item.desc}</div>
                                </div>
                            </div>
	                    `;
	                });

	                html += '</div></div>';

	                content.html( html );
                    codevzTemplatesCache = html;

	            } else {
	                content.html('<p>No templates found.</p>');
	            }
	        }
	    });
	}

    function tryAddTabAndContent() {
        const modal = jQuery( '.dialog-widget.elementor-templates-modal:visible' );
        const menu = modal.find( '#elementor-template-library-header-menu' );
        const container = modal.find( '#elementor-template-library-templates' );

        if ( ! modal.length || ! menu.length || ! container.length ) {
            return false;
        }

        if ( ! menu.find( '[data-tab="templates/codevz-plus"]' ).length ) {
            menu.append( '<div class="elementor-component-tab elementor-template-library-menu-item" data-tab="templates/codevz-plus">Codevz Templates</div>' );
        }

        if ( ! jQuery( '#codevz-plus-content' ).length ) {

            const customContent = jQuery( '<div id="codevz-plus-content" style="display:none;"></div>' );
            container.after( customContent );

            // Filter logic
            jQuery(document).on( 'click', '.codevz-filters a', function() {

                var $this  = $( this ),
                    filter = $this.text().trim().toLowerCase();

                $this.addClass( 'codevz-active' ).siblings().removeClass( 'codevz-active' );

                if ( $this.is( ':first-child' ) ) {
                    filter = 'all';
                }

                jQuery('#codevz-plus-content .elementor-template-library-template').each(function() {
                    const desc = jQuery(this).find('.codevz-elementor-templates-keywords').text().toLowerCase();
                    if (filter === 'all' || desc.includes(filter)) {
                        jQuery(this).show();
                    } else {
                        jQuery(this).hide();
                    }
                });

            });

            // Search logic
            jQuery( document ).on( 'input', '.codevz-search-templates input', function() {
                const val = jQuery( this ).val().toLowerCase();
                jQuery( '#codevz-plus-content .elementor-template-library-template' ).each( function() {
                    const keywords = jQuery( this ).find( '.codevz-elementor-templates-keywords' ).text().toLowerCase();
                    jQuery( this ).toggle( keywords.includes( val ) );
                });
            });
        }

        return true;
    }

    function activateTab(tabName) {
        const modal = jQuery('.dialog-widget.elementor-templates-modal:visible');
        if (!modal.length) {
            return;
        }

        modal.find('#elementor-template-library-header-menu .elementor-template-library-menu-item').removeClass('elementor-active');

        if (tabName === 'templates/codevz-plus') {
            modal.find('[data-tab="templates/codevz-plus"]').addClass('elementor-active');
            modal.find('#elementor-template-library-templates').hide();
            jQuery('#codevz-plus-content').show();
        } else {
            modal.find('[data-tab="' + tabName + '"]').addClass('elementor-active');
            modal.find('#elementor-template-library-templates').show();
            jQuery('#codevz-plus-content').hide();
        }
    }

    const generateId = () => Math.random().toString(36).slice(2, 9);

    function cloneAndAddTemplate(template) {
        const doc = elementor.documents.getCurrent();
        if (!doc) {
            return alert('Elementor document not ready.');
        }

        const containerView = doc.container.view;
        if (!containerView?.collection) {
            return alert('Cannot access container view.');
        }

        // deep clone
        const cloned = JSON.parse(JSON.stringify(template));

        if (cloned.content?.length) {
            cloned.content.forEach(section => {
                section.id = generateId();

                if (Array.isArray(section.elements)) {
                    section.elements.forEach(child => {
                        child.id = generateId();
                    });
                }

                // append each section in order
                containerView.addChildModel(section, { at: containerView.collection.length });
            });
        }

        // notify elementor
        elementor.channels.data.trigger('element:add', cloned.content);
        elementor.channels.data.trigger('change', { source: 'codevz-plus-insert' });

        elementor.notifications.showToast({ message: '✅ Template Added!' });
    }

    function observeModal() {
        const modal = document.querySelector('.dialog-widget.elementor-templates-modal');
        if (!modal) return;

        const observer = new MutationObserver(() => {
            if (tryAddTabAndContent()) {
                const menu = jQuery(modal).find('#elementor-template-library-header-menu');

                // Custom tab click
                menu.off('click.codevz').on('click.codevz', '[data-tab="templates/codevz-plus"]', function () {
                    activateTab('templates/codevz-plus');
                    loadCodevzTemplates();
                });

                if (!tabInitialized) {
                    tabInitialized = true;
                    jQuery('[data-tab="templates/codevz-plus"]').trigger('click');
                }

                // Default tabs click
                menu.off('click.codevz-default').on('click.codevz-default', '.elementor-template-library-menu-item:not([data-tab="templates/codevz-plus"])', function () {
                    const tabName = jQuery(this).data('tab');
                    setTimeout(() => {
                        activateTab(tabName);
                    }, 200);
                });

                // Insert template.
                jQuery(document).off('click.insert').on('click.insert', '.codevz-insert-btn', function () {

                    const modal = jQuery('.dialog-widget.elementor-templates-modal:visible');
                    const loader = modal.find('.dialog-loading');
                    const content = jQuery('#codevz-plus-content');
                    const templateId = jQuery(this).data('id');

                    content.hide();
                    loader.show();

                    jQuery.ajax({
                        url: codevzData.ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'codevz_fetch_template_json',
                            template_id: templateId,
                            security: codevzData.nonce
                        },
                        success: function(res) {
                            if (!res.success) {
                                return alert('Failed to load template JSON');
                            }

                            let data = res.data, queue = [];

                            console.log( data );

                            (function collect(o){
                                for (let k in o) {
                                    if (!o.hasOwnProperty(k)) continue;
                                    if (k === 'xtra_elementor_template' && /^https?:\/\//.test(o[k])) {
                                        queue.push({ parent:o, key:k, url:o[k] });
                                    } else if (typeof o[k] === 'object' && o[k]) {
                                        collect(o[k]);
                                    }
                                }
                            })(data);

                            // process one by one
                            function next() {
                                if (!queue.length) {
                                    jQuery('.elementor-templates-modal__header__close i').trigger('click');
                                    content.show();
                                    loader.hide();
                                    cloneAndAddTemplate(data);
                                    return;
                                }
                                const item = queue.shift();
                                jQuery.post(codevzData.ajaxurl, {
                                    action: 'codevz_import_inner_template',
                                    template_url: item.url,
                                    security: codevzData.nonce
                                }, r => {
                                    if (r.success && r.data.id) {
                                        item.parent[item.key] = r.data.id;
                                    }
                                    next();
                                });
                            }
                            next();
                        }

                    });
                });

            }
        });

        observer.observe(modal, { childList: true, subtree: true });
    }



    function addCustomTab() {
        const nav = $('#elementor-panel-elements-navigation'),
              wrapper = $('#elementor-panel-elements-wrapper');
        if (!nav.length || !wrapper.length) {
            return;
        }

        if (!nav.find('[data-tab="templates"]').length) {
            nav.append('<button class="elementor-component-tab elementor-panel-navigation-tab" data-tab="templates">Templates</button>');
        }

        // 🔹 Attach click listener once
        nav.off('click.templates').on('click.templates', '[data-tab="templates"]', function () {
            // Trigger the folder icon inside the preview iframe
            let iframe = $('#elementor-preview-iframe')[0];
            let doc = iframe?.contentDocument;
            if (doc) {
                $(doc).find('.elementor-add-section-area-button.elementor-add-template-button').trigger('click');
            }
        });
    }

    function waitForElementor(cb) {
        if (window.elementor?.documents?.getCurrent()) {
            cb();
        } else {
            setTimeout(() => waitForElementor(cb), 500);
        }
    }

    function observePanel() {
        const panel = document.querySelector('#elementor-panel');
        if (!panel) {
            return;
        }
        new MutationObserver(addCustomTab).observe(panel, { childList:true, subtree:true });
    }

    function continuousCheck() {
        addCustomTab();
        setTimeout(continuousCheck, 2000);
    }

    waitForElementor(() => {
        observePanel();
        continuousCheck();
    });


    (function checkTemplates() {
        let doc = jQuery('#elementor-preview-iframe')[0]?.contentDocument;
        if (doc && jQuery(doc).find('.elementor-add-section-area-button.elementor-add-template-button').length) {
            jQuery(doc).on('click', '.elementor-add-section-area-button.elementor-add-template-button', () => {
                observeModal();
                setTimeout(() => jQuery('[data-tab="templates/codevz-plus"]').trigger('click'), 100);
            });
            return;
        }
        setTimeout(checkTemplates, 1000);
    })();

});