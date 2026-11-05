jQuery(document).ready(function ($) {
    // variables
    let iContent,
        liveEditWrapper = $(document).find('.live-edit-wrapper'),
        frame = liveEditWrapper.find('.content #live-edit-content'),
        nonce = liveEditWrapper.attr('mwpl-nonce')

    // functions
    function handle_deps()
    {
        let has_deps = iContent.find('[depends]')
        if(has_deps.length)
        {
            has_deps.each((index, item) => {
                let el = $(item),
                    deps = el.attr('depends'),
                    deps_el = iContent.find('#' + deps)
                if(deps_el.length)
                {
                    deps_el.on('change', {item: el}, e => {
                        let mw_this = $(e.target)
                        check_deps(mw_this, e.data.item)
                    })
                    check_deps(deps_el, el)
                }
            })
        }
    }
    function check_deps(deps_el, item_el)
    {
        let tagName = deps_el.prop('tagName'),
            state = false

        if(tagName == 'SELECT')
        {
            let value = deps_el.find(':selected').val()
            state = value == item_el.attr('depends_value')
        }else if(tagName == 'INPUT')
        {
            let type = deps_el.attr('type')
            if(type == 'checkbox')
            {
                state = deps_el.is(":checked");
            }
        }
        if(state)
        {
            // item_el.slideDown()
            item_el.removeClass('hide')
        }else{
            // item_el.slideUp()
            item_el.addClass('hide')
        }
    }
    function showNotification(msg, type='error')
    {
        let notificationWrapper = liveEditWrapper.find('.notification-wrapper'),
            notificationItem = $('<div>')
        notificationItem.addClass('notice-item')
        notificationItem.attr('type', type)
        notificationItem.text(msg)
        notificationWrapper.append(notificationItem)
        setTimeout(() => {
            notificationItem.remove()
        }, 3000);
    }
    function disableMenuItems() {
        let menuWrapper = iContent.find('.mihanpanel-page .mpsidebar .nav.mp-nav-tabs.menu-tabs-items')
        menuWrapper.css({ 'pointer-events': 'none', 'opacity': '.5' })
    }
    function enableMenuItems() {
        let menuWrapper = iContent.find('.mihanpanel-page .mpsidebar .nav.mp-nav-tabs.menu-tabs-items')
        menuWrapper.css({ 'pointer-events': '', 'opacity': '' })
    }
    function enableIframeLoadingMode() {
        liveEditWrapper.find('.content').addClass('preload-mode')
    }
    function disabledIframeLoadingMode() {
        liveEditWrapper.find('.content').removeClass('preload-mode')
    }

    function renderMenuItems() {
        let menuWrapper = iContent.find('.mihanpanel-page .mpsidebar .nav.mp-nav-tabs.menu-tabs-items')
        disableMenuItems()
        $.ajax({
            url: mwp_data.au,
            type: 'post',
            dataType: 'json',
            data: {
                mwpl_nonce: nonce,
                action: 'mwpl_live_edit_tabs_fields_get_items'
            },
            success: response => {
                if (response.status === 200) {
                    menuWrapper.html(response.data)
                }
                enableMenuItems()
            },
            error: err => {
                enableMenuItems()
            }
        })
    }
    function addNewItemBtnInMenu() {
        let logoutMenuWrapper = iContent.find('.mihanpanel-page .mpsidebar .nav.mp-nav-tabs.logout-menu')
        let newItem = $('<li>'),
            newItemName = mwp_data.texts.new_item
        newItem.html(`<a><p>${newItemName}</p></a>`)
        newItem.addClass('live-edit-sidebar-new-menu-item')
        logoutMenuWrapper.prepend(newItem)
    }
    function addNewItemBtnInMenuHandler() {
        let menuItemsWrapper = iContent.find('.mihanpanel-page .mpsidebar .nav.mp-nav-tabs.menu-tabs-items'),
            defaultItems = iContent.find('.mihanpanel-page .mpsidebar .nav.mp-nav-tabs.default-menu li'),
            lastItem = defaultItems[defaultItems.length - 1],
            clone = $(lastItem).clone()
        clone.find('p').html(`<input placeholder="${mwp_data.texts.item_name}" />`)
        clone.attr('class', 'new-item-mode')
        menuItemsWrapper.append(clone)
        clone.find('input').focus()
        // let id = (new Date()).getTime()
        // clone.find('p').text('[[Name]]')
        // clone.attr('tab-id', id)
        // clone.find('a').removeAttr('href')
        // menuItemsWrapper.append(clone)
    }
    function addEditContentBtn()
    {
        let title = iContent.find('.mihanpanel-page .main-panel#tab-field-content .mihanpanel-section-title')
        if(!title.length)
        {
            return
        }
        let btn = $('<span>')
        btn.addClass('edit-content-btn')
        btn.text(mwp_data.texts.edit_content_btn_text)
        title.append(btn)

        enableIframeLoadingMode()
        $.ajax({
            url: mwp_data.au,
            type: 'post',
            dataType: 'json',
            data: {
                action: 'mwpl_live_edit_tabs_fields_get_item_conten_fields',
                mwpl_nonce: nonce,
                item_id: frame.attr('mwpl_current_tab'),
            },
            success: response => {
                if(response.status === 200)
                {
                    title.after(response.content)
                    handle_deps()
                }
            },
            complete: () => {
                disabledIframeLoadingMode()
            }
        })
    }
    function updatePriority() {
        let items = iContent.find('.mihanpanel-page .mpsidebar .nav.mp-nav-tabs.menu-tabs-items li')
        let data = []
        items.each((index, value) => {
            let el = $(value)
            data[index] = el.attr('tab-id')
        })
        $.ajax({
            url: mwp_data.au,
            type: 'post',
            dataType: 'json',
            data: {
                mwpl_nonce: nonce,
                action: 'mwpl_live_edit_tabs_fields_update_priorities',
                data: data,
            },
            success: response => {

            },
            error: err => {
                showNotification(mwp_data.texts.has_error, 'error')
            }
        })
    }

    function overrideIframeStyle() {
        let head = frame.contents().find('head'),
            styles = mwp_data.assets !== undefined && mwp_data.assets.styles !== undefined ? mwp_data.assets.styles : false
        if(typeof styles !== 'object' || !Object.keys(styles).length)
        {
            return;
        }
        Object.values(styles).forEach((value, index) => {
            link = $('<link>'),
            link.attr('rel', 'stylesheet')
                .attr('href', value)
            head.append(link)
        })
    }

    // events
    frame.on('load', function () {
        disabledIframeLoadingMode()
        iContent = frame.contents().find('body')
        renderMenuItems()
        overrideIframeStyle()
        addNewItemBtnInMenu()
        addEditContentBtn()
        iContent.find('.mihanpanel-page .mpsidebar .nav.mp-nav-tabs.menu-tabs-items').addClass('mw_sortable').sortable({
            cursor: 'move',
            update: () => updatePriority(),
        })

        iContent.find('.live-edit-sidebar-new-menu-item').on('click', function (e) {
            e.preventDefault()
            addNewItemBtnInMenuHandler()
        })
        // .mihanpanel-page a
        iContent.find('.mihanpanel-page a').on('click', function (e) {
            e.preventDefault()
            // e.stopPropagation()
        })
        iContent.on('change', '.mihanpanel-page .mpsidebar .nav.mp-nav-tabs.menu-tabs-items li[tab-id] p input', function (e) {
            let mwthis = $(this),
                tabID = mwthis.closest('li').attr('tab-id')

            if (!mwthis.val().length) {
                return
            }
            disableMenuItems()
            // TODO: need notification
            $.ajax({
                url: mwp_data.au,
                type: 'post',
                dataType: 'json',
                data: {
                    action: 'mwpl_live_edit_tabs_fields_edit_item_title',
                    mwpl_nonce: nonce,
                    item_id: tabID,
                    title: mwthis.val(),
                },
                success: response => {
                    enableMenuItems()
                    let type = 'error'
                    if(response.status === 200)
                    {
                        type = 'success'
                    }
                    showNotification(response.msg, type)
                },
                error: err => {
                    showNotification(mwp_data.texts.has_error, 'error')
                }
            })
        })
        iContent.on('change', '.mihanpanel-page .mpsidebar .nav.mp-nav-tabs.menu-tabs-items li.new-item-mode p input', function (e) {
            let mwthis = $(this)
            if (!mwthis.val().length) {
                mwthis.closest('li').remove()
                return
            }
            let currentNumber = mwthis.closest('ul').find('li').length;
            disableMenuItems()
            // TODO: need notification
            $.ajax({
                url: mwp_data.au,
                type: 'post',
                dataType: 'json',
                data: {
                    action: 'mwpl_live_edit_tabs_fields_add_new_item',
                    mwpl_nonce: nonce,
                    title: mwthis.val(),
                    index: currentNumber - 1,
                    icon: mwthis.closest('li').find('i').attr('class'),
                },
                success: response => {
                    // let li = mwthis.closest('li')
                    // if(response.status === 200)
                    // {
                    //     // remove li new-item-mode class
                    //     li.removeAttr('class')
                    //     li.attr('tab-id', response.item_id)
                    // }else{
                    //     li.remove()
                    // }
                    renderMenuItems()
                    let type = 'error'
                    if(response.status === 200)
                    {
                        type = 'success'
                    }
                    showNotification(response.msg, type)
                },
                error: err => {
                    showNotification(mwp_data.texts.has_error, 'error')
                }
            })
        })
        iContent.on('click', '.mihanpanel-page .mpsidebar .nav.mp-nav-tabs.menu-tabs-items li .remove-icon', function (e) {
            e.preventDefault()
            e.stopPropagation()
            if (!confirm(mwp_data.texts.delete_msg)) {
                return false;
            }
            let mwthis = $(this),
                item = mwthis.closest('li'),
                itemId = item.attr('tab-id')

            disableMenuItems()
            $.ajax({
                url: mwp_data.au,
                type: 'post',
                dataType: 'json',
                data: {
                    mwpl_nonce: nonce,
                    action: 'mwpl_live_edit_tabs_fields_delete_item',
                    item_id: itemId,
                },
                success: response => {
                    enableMenuItems()
                    if (response.status === 200) {
                        item.remove()
                    } else {
                        alert(response.msg)
                    }
                },
            })
        })
        iContent.on('click', '.mihanpanel-page .mpsidebar .nav.mp-nav-tabs.menu-tabs-items li .edit-icon', function (e) {
            e.preventDefault()
            e.stopPropagation()
            let mwthis = $(this),
                link = mwthis.closest('li').find('a').attr('mwpl-href')
            if (link.length > 0) {
                enableIframeLoadingMode()
                frame.attr('src', link)
                frame.attr('mwpl_current_tab', mwthis.closest('li').attr('tab-id'))
            }
        })
        iContent.on('click', '.mihanpanel-page .mpsidebar .nav.mp-nav-tabs.menu-tabs-items li i', function (e) {
            e.preventDefault()
            e.stopPropagation()
            let mwthis = $(this),
                iconField = $('<input>');

            mwthis.mwpl_iconpicker2(iconField);
			
            iconField.on('change', function () {
                disableMenuItems();
                $.ajax({
                    url: mwp_data.au,
                    type: 'post',
                    dataType: 'json',
                    data: {
                        action: 'mwpl_live_edit_tabs_fields_edit_item_icon',
                        mwpl_nonce: nonce,
                        item_id: mwthis.closest('li').attr('tab-id'),
						icon: $(this).val()
                    },
                    success: response => {
                        let type = 'error'
                        if (response.status === 200) {
                            mwthis.attr('class', $(this).val())
                            type = 'success'
                        }
                        showNotification(response.msg, type)
                    },
                    complete: (xhr, reqStatus) => {
                        enableMenuItems()
                    }
                })
            })
        })
        iContent.on('click', '.mihanpanel-page .main-panel#tab-field-content .mihanpanel-section-title .edit-content-btn', function (e) {
            if(frame.attr('mwpl_current_tab') === undefined)
            {
                return
            }
            let editWrapper = iContent.find('.mp-content.edit-fields-wrapper'),
                currentContent = iContent.find('.mp-content:not(.edit-fields-wrapper)')

            editWrapper.slideDown('fast', function(){
                currentContent.slideUp('fast')
            })
        })
        iContent.on('click', '.mihanpanel-page #tab-field-content .edit-fields-wrapper #save_edit_field_content', function(e){
            let mwthis = $(this),
                contentField = mwthis.closest('.edit-fields-wrapper').find('textarea')
            if(!contentField.length)
            {
                return
            }
            let formData = mwthis.closest('form').serialize()
            if(!formData.length)
            {
                return
            }
            enableIframeLoadingMode()
            // TODO: need notification
            $.ajax({
                url: mwp_data.au,
                type: 'post',
                dataType: 'json',
                data: {
                    action: 'mwpl_live_edit_tabs_fields_edit_item_content',
                    mwpl_nonce: nonce,
                    item_id: frame.attr('mwpl_current_tab'),
                    field_data: formData,
                },
                success: response => {
                    if(response.status === 200)
                    {
                        // let editWrapper = iContent.find('.mp-content.edit-fields-wrapper'),
                        //     currentContent = iContent.find('.mp-content:not(.edit-fields-wrapper)')

                        // currentContent.slideDown('fast', function(){
                        //     editWrapper.slideUp('fast')
                        // })
                        frame.attr('src', frame.attr('src'))
                    }
                },
                complete: err => {
                    disabledIframeLoadingMode()
                }
            })
        })
    })
})