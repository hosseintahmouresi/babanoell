jQuery(document).ready(function($){
    // variables
    const CONDITION_TYPE_SHOW_TO_ALL = 0;
    const CONDITION_TYPE_USERNAME = 1;
    const CONDITION_TYPE_ROLE = 2;


    // run
    let currentConditionType = $(document).find("#condition_type").val()
    if(currentConditionType == CONDITION_TYPE_USERNAME)
    {
        $(document).find('input[name=username]').closest('tr[handle_render]').show()
    }else if(currentConditionType == CONDITION_TYPE_ROLE)
    {
        $(document).find('label[for=roles]').closest('tr[handle_render]').show()
    }
    
    // events
    $(document).find('#condition_type').on('change', function(){
        let el = $(this)
        let allFields = $(document).find('tr[handle_render]')
        allFields.hide()
        if(el.val() == CONDITION_TYPE_USERNAME)
        {
            $(document).find('input[name=username]').closest('tr[handle_render]').show()
        }
        if(el.val() == CONDITION_TYPE_ROLE)
        {
            $(document).find('label[for=roles]').closest('tr[handle_render]').show()
        }
    })
})