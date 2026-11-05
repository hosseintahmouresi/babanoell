<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders the Cooperation Contract Settings page in the admin panel.
 */
function mlr_cooperation_contract_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die('شما دسترسی لازم برای مشاهده این صفحه را ندارید.');
    }

    // Enqueue media scripts for image uploaders
    wp_enqueue_media();

    $options = get_option('mlr_cooperation_contract_settings', [
        'header_image_url' => '',
        'footer_image_url' => '',
        'contract_text'    => '',
    ]);

    // *** CHANGED to the new function name ***
    $cooperation_placeholders = mlr_get_contract_placeholders();

    ?>
    <div class="wrap mlr-admin-wrap mlr-contract-settings-wrap">
        <div class="mlr-page-header">
             <h1>تنظیمات قرارداد همکاری</h1>
        </div>
        <p>در این بخش می‌توانید سربرگ، پابرگ و متن پیش‌فرض قرارداد همکاری با پذیرندگان را تنظیم کنید.</p>

        <?php if (isset($_GET['settings-updated'])) : ?>
            <div id="message" class="updated notice is-dismissible"><p>تنظیمات با موفقیت ذخیره شد.</p></div>
        <?php endif; ?>

        <form method="post" action="options.php">
            <?php settings_fields('mlr_cooperation_contract_settings_group'); ?>

            <div class="mlr-settings-card">
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label>سربرگ قرارداد</label></th>
                            <td>
                                <div class="mlr-image-uploader">
                                    <div class="mlr-image-preview">
                                        <?php if (!empty($options['header_image_url'])) : ?>
                                            <img src="<?php echo esc_url($options['header_image_url']); ?>" style="max-width: 300px; max-height: 150px; display: block; margin-bottom: 10px;" />
                                        <?php endif; ?>
                                    </div>
                                    <input type="hidden" name="mlr_cooperation_contract_settings[header_image_url]" class="mlr-image-url" value="<?php echo esc_attr($options['header_image_url']); ?>" />
                                    <button type="button" class="button mlr-upload-image-button">انتخاب / آپلود تصویر</button>
                                    <button type="button" class="button mlr-remove-image-button" style="<?php echo empty($options['header_image_url']) ? 'display:none;' : ''; ?>">حذف تصویر</button>
                                </div>
                                <p class="description">تصویر بالای صفحه قرارداد.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mlr_contract_text">متن قرارداد</label></th>
                            <td>
                                <?php
                                $editor_settings = [
                                    'textarea_name' => 'mlr_cooperation_contract_settings[contract_text]',
                                    'media_buttons' => false,
                                    'textarea_rows' => 15,
                                    'teeny'         => false, // Use the full editor
                                    'quicktags'     => true
                                ];
                                wp_editor(wp_kses_post($options['contract_text']), 'mlr_contract_text_editor', $editor_settings);
                                ?>
                                <div class="mlr-variable-guide" style="margin-top: 15px;">
                                    <strong>متغیرهای در دسترس برای استفاده در متن:</strong>
                                    <div class="mlr-placeholders-list">
                                        <?php foreach($cooperation_placeholders as $ph => $desc): ?>
                                            <span class="mlr-placeholder-tag" title="<?php echo esc_attr($desc); ?>"><?php echo esc_html($ph); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="description">این متغیرها هنگام نمایش قرارداد با اطلاعات درخواست مربوطه جایگزین می‌شوند.</p>
                                </div>
                            </td>
                        </tr>
                         <tr>
                            <th scope="row"><label>پابرگ قرارداد</label></th>
                            <td>
                                <div class="mlr-image-uploader">
                                     <div class="mlr-image-preview">
                                        <?php if (!empty($options['footer_image_url'])) : ?>
                                            <img src="<?php echo esc_url($options['footer_image_url']); ?>" style="max-width: 300px; max-height: 100px; display: block; margin-bottom: 10px;" />
                                        <?php endif; ?>
                                    </div>
                                    <input type="hidden" name="mlr_cooperation_contract_settings[footer_image_url]" class="mlr-image-url" value="<?php echo esc_attr($options['footer_image_url']); ?>" />
                                    <button type="button" class="button mlr-upload-image-button">انتخاب / آپلود تصویر</button>
                                    <button type="button" class="button mlr-remove-image-button" style="<?php echo empty($options['footer_image_url']) ? 'display:none;' : ''; ?>">حذف تصویر</button>
                                </div>
                                <p class="description">تصویر پایین صفحه قرارداد.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php submit_button('ذخیره تنظیمات'); ?>
        </form>
    </div>

    <style>
        .mlr-contract-settings-wrap .mlr-settings-card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; margin-bottom: 30px; }
        .mlr-contract-settings-wrap .form-table th { width: 180px; padding-right: 25px; vertical-align: top; padding-top: 25px;}
        .mlr-contract-settings-wrap .form-table td { padding: 20px 25px; }
        .mlr-image-uploader { display: flex; flex-direction: column; align-items: flex-start; gap: 10px; }
        .mlr-variable-guide { background-color: #f0f6fc; border: 1px solid #d0e2f2; padding: 15px; border-radius: 6px; }
        .mlr-variable-guide strong { font-weight: 600; color: #1e40af; }
        .mlr-placeholders-list { margin-top: 10px; display: flex; flex-wrap: wrap; gap: 8px; }
        .mlr-placeholder-tag { background-color: #dbeafe; color: #1e40af; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-family: monospace; cursor: help; }
        .mlr-contract-settings-wrap .wp-editor-wrap { border: 1px solid #dcdcde; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
    </style>

    <script>
    jQuery(document).ready(function($){
        // Media Uploader Script
        $('.mlr-image-uploader').each(function(){
            var uploaderContainer = $(this);
            var uploadButton = uploaderContainer.find('.mlr-upload-image-button');
            var removeButton = uploaderContainer.find('.mlr-remove-image-button');
            var imageInput = uploaderContainer.find('.mlr-image-url');
            var previewContainer = uploaderContainer.find('.mlr-image-preview');
            var frame;

            uploadButton.on('click', function( event ){
                event.preventDefault();
                if ( frame ) {
                    frame.open();
                    return;
                }
                frame = wp.media({
                    title: 'انتخاب یا آپلود تصویر',
                    button: { text: 'استفاده از این تصویر' },
                    multiple: false
                });
                frame.on( 'select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    imageInput.val(attachment.url);
                    previewContainer.html('<img src="' + attachment.url + '" style="max-width: 300px; max-height: 150px; display: block; margin-bottom: 10px;" />');
                    removeButton.show();
                });
                frame.open();
            });

            removeButton.on('click', function( event ){
                event.preventDefault();
                imageInput.val('');
                previewContainer.html('');
                $(this).hide();
            });
        });
    });
    </script>
    <?php
}