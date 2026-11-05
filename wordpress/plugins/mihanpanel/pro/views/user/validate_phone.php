<?php
$cuid = get_current_user_id();
$has_code = \mihanpanel\pro\app\dynamic_code::user_has_valid_code($cuid, $expiration_time);
?>
<div class="main-panel mwtabb" id="profile">
    <div class="mp-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="mihanpanel-card">
                        <div class="mihanpanel-card-header" data-background-color="purple">
                            <h4 class="title"><?php esc_html_e("Phone Number Validation", 'mihanpanel'); ?></h4>
                            <p class="category"><?php esc_html_e("Validate your phone", "mihanpanel"); ?></p>
                        </div>

                        <div class="mihanpanel-card-content">
                            
                            <form method="post">
                                <div class="row">
                                    <div class="col-md-7" style="margin: 0 auto;">
                                        <div class="form-group label-floating">
                                            <label class="control-label"><?php esc_html_e("Phone Number", "mihanpanel"); ?></label>
                                            <input autocomplete="off" type="number" id="mw_phone_number"
                                                   value="<?php echo esc_attr(\mihanpanel\pro\app\users::get_user_phone($cuid)); ?>"
                                                   class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-12 mw_dynamic_code_wrapper" style="text-align: center; margin: 0 auto;">
                                        <div class="form-group label-floating">
                                            <label class="control-label"><?php esc_html_e("Dynamic Code", "mihanpanel"); ?></label>
                                            <div class="mw_dynamic_code_input_box">
                                                <input class="mw_dyna_code" type="number">
                                            </div>
                                            <small class="res_msg"></small>
                                        </div>
                                        <button <?php echo $has_code ? 'disabled' : '';?> name="submit" mw_mode="<?php echo $has_code ? 'validate' : 'request'; ?>" id="mw_get_dynamic_code" type="submit" class="btn btn-primary pull-right">
                                            <span class="<?php echo $has_code ? 'display_none' : '';?>" id="mw_request_code_text"><?php esc_html_e("Request Code", "mihanpanel"); ?></span>
                                            <span class="<?php echo $has_code ? '' : 'display_none';?>" exp="<?php echo $has_code ? esc_attr(date('Y-m-d H:i:s', $expiration_time)) : ''; ?>" id="mw_timer">00 : 00</span>
                                            <span class="display_none mce-ico dashicons dashicons-upload" id="mw_submit_icon"></span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>