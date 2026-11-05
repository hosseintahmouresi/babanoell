<div class="wrap">
    <div class="license-wrapper">
        <div class="logo-section">
            <img src="<?php echo esc_url(\mihanpanel\app\assets::get_image_url('logo', 'svg')); ?>" alt="mihanpanel-logo">
        </div>
        <div class="license-box">
        <?php
        $license_status = \mihanpanel\pro\app\license::get_license_status();
        ?>
            <p class="<?php echo esc_attr($license_status['class']); ?> status-bar"><?php echo esc_html($license_status['msg']); ?></p>
            <label for="license-key"><?php esc_html_e("License Key", 'mihanpanel')?></label>
            <div class="field-wrapper">
                <span notice-type="success" class="mwpl_notice"></span>
            </div>
            <div class="field-wrapper">
                <input value="<?php echo $license_key ? esc_attr($license_key) : ''; ?>" type="text" dir="auto" id="license-key" name="license-key">
                <span class="display_none loading dashicons dashicons-marker"></span>
            </div>
            
            <div class="action-bar">
                <input type="submit" process-type="activate" class="<?php echo $license_status['class'] == 'active' ? 'display_none' : ''; ?> process_license activate btn" name="activate_license" value="<?php esc_html_e('Activate License', 'mihanpanel')?>">
                <input type="submit" process-type="deactivate" class="<?php echo $license_status['class'] == 'deactive' ? 'display_none' : ''; ?> process_license deactivate btn" name="activate_license" value="<?php esc_html_e('Deactivate License', 'mihanpanel')?>">        
                <a class="btn get-license" target="_blank" href="https://mihanwp.com/mwpanel/?tab=licenses"><?php esc_html_e('Get License Key', 'mihanpanel')?></a>        
            </div>
        
        </div>
    </div>
</div>