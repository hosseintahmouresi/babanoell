<form class="mp-content edit-fields-wrapper hide">
    <div class="row-field">
        <label for="linkorcontent"><?php esc_html_e('Content', 'mihanpanel') ?></label>
        <select id="linkorcontent" name="linkorcontent">
            <option selected disabled><?php esc_html_e("Choose", "mihanpanel"); ?></option>
            <option value="optionlink"><?php esc_html_e("Link to other page", "mihanpanel") ?></option>
            <option value="option_shortcode"><?php esc_html_e("Shortcode", "mihanpanel"); ?></option>
            <option value="text_content"><?php esc_html_e('Text content', 'mihanpanel')?></option>
        </select>
    </div>

    <div class="row-field" depends="linkorcontent" depends_value='optionlink'>
        <label for="islink"><?php esc_html_e('Link', 'mihanpanel') ?></label>
        <input type="text" id="islink" style="direction:ltr;text-align:left" value="<?php echo esc_attr($fieldData->link); ?>" name="link" placeholder="http://" />
    </div>
    <div class="row-field" depends="linkorcontent" depends_value='option_shortcode'>
        <?php $shortcodes = \mihanpanel\app\tools::get_all_shortcodes(); ?>
        <label for="shortcode_list"><?php esc_html_e('Shortcode', 'mihanpanel') ?></label>
        <select id="shortcode_list" name="shortcode_list">
            <?php if ($shortcodes) : ?>
                <option value="0" selected disabled><?php esc_html_e('Choose your item', 'mihanpanel') ?></option>
                <option value="mwp_custom_shortcode"><?php esc_html_e('Custom Shortcode', 'mihanpanel') ?></option>
                <?php foreach ($shortcodes as $code => $function) : ?>
                    <option value="[<?php echo esc_attr($code); ?>]"><?php echo esc_html($code); ?></option>
                <?php endforeach; ?>
            <?php else : ?>
                <option value="0"><?php esc_html_e('No any shortcodes found.', 'mihanpanel') ?></option>
            <?php endif; ?>
        </select>
    </div>
    <div class="row-field" depends="shortcode_list" depends_value="mwp_custom_shortcode">
        <label for="custom_shortcode_value"><?php esc_html_e('Custom Shortcode', 'mihanpanel') ?></label>
        <input type='text' name="custom_shortcode_value" id="custom_shortcode_value" placeholder="[your_shortcode]">
    </div>


    <div class="row-field" depends="linkorcontent" depends_value="text_content">
        <label for="item_content"><?php esc_html_e('Content', 'mihanpanel') ?></label>
        <textarea name="item_content" id="item_content"><?php echo esc_textarea($fieldData->content) ?></textarea>
    </div>
    <div class="row-field">
        <span id="save_edit_field_content"><?php esc_html_e('Save', 'mihanpanel') ?></span>
    </div>
</form>