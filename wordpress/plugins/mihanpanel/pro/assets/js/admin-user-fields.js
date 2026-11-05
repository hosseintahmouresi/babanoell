jQuery(document).ready(function($){
    function get_id(idWrapper)
    {
        let value = parseInt(idWrapper.val()) + 1;
        idWrapper.val(value);
        return value;
    }
    // functions
    function removeHandler(count, item)
    {
        item.closest('.mw_sortable').find('input').trigger('input');
        // check current item count
        if(count == 1)
        {
            item.find('input').val('');
            return false;
        }
        item.fadeOut('slow', function(){
            item.remove();
        });
    }
    function addNewHandler(fieldWrapper, item)
    {
        let idWrapper = fieldWrapper.find('input[name=select-index]'),
            newId = get_id(idWrapper),
            newItem = item.clone();

        newItem.find('input').val('');
        
        let idSection = newItem.find('.id-field'),
            titleSection = newItem.find('.title-field');
        
        idSection.find('input').attr('id', 'select-id-' + newId);
        idSection.find('label').attr('for', 'select-id-' + newId);
        
        titleSection.find('label').attr('for', 'select-title-' + newId);
        titleSection.find('input').attr('id', 'select-title-' + newId);
        idSection.find('input').attr('name', `sub_item[${newId}][id]`);
        titleSection.find('input').attr('name', `sub_item[${newId}][title]`);

        fieldWrapper.append(newItem);
    }
    function handleSelectChange(el, fieldWrapper)
    {
        fieldWrapper.each(function(index, value){
            let valEl = $(value),
                status = valEl.attr('type_' + el.value)
            if(status != undefined)
            {
                if(el.value == 'selectbox' || el.value == 'radio')
                {
                    valEl.find('.sub_row_title').text(el.options[el.selectedIndex].text)
                }
                // slide down
                valEl.slideDown();
            }else{
                // slide up
                valEl.slideUp();
            }
        });
    }
    // events
    $('.mw_menus_table .mw_rows select[name=type]').on('change', function(e){
        // let fieldWrapper = $(this).closest('.mw_row').next('.field-meta-wrapper');
        let fieldWrapper = $(this).closest('.mw_rows').find('.field-meta-wrapper');
        handleSelectChange(this, fieldWrapper);
    })
    $('.mw_menus_table.new_record select[name=type]').on('change', function(e){
        let selectModeFields = $(this).closest('.mw_menus_table').find('.field-meta-wrapper');
        handleSelectChange(this, selectModeFields)
    });
    $('.selectbox-type-item .add-new').on('click', function(e){
        e.preventDefault();
        let mwthis = $(this),
            fieldWrapper = mwthis.closest('td'),
            item = fieldWrapper.find('.item').first();
        addNewHandler(fieldWrapper, item)
    });
    $('.field-meta-wrapper .add-new').on('click', function(e){
        e.preventDefault();
        let mwthis = $(this),
            fieldWrapper = mwthis.closest('.field-meta-wrapper'),
            item = fieldWrapper.find('.mw_sub_row.field-item').first();
        addNewHandler(fieldWrapper, item);
    });
    
    $(document).on('click', '.selectbox-type-item .item .remove-btn', function(e){
        e.preventDefault();
        let count = $('.selectbox-type-item .item').length,
            mwthis = $(this),
            item = mwthis.closest('.item');
        removeHandler(count, item);
    });
    $(document).on('click', '.field-meta-wrapper .remove-btn', function(e){
        e.preventDefault();
        let mwthis = $(this),
            count = mwthis.closest('.field-meta-wrapper').find('.mw_sub_row.field-item').length,
            item = mwthis.closest('.mw_sub_row.field-item');
        removeHandler(count, item);
    });
});