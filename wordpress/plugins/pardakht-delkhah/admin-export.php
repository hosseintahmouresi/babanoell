<?php
defined('ABSPATH') or die('No script kiddies please!');
?>
<div class="wrap">
    <h2><?php echo __('Export'); ?></h2>
    <hr>
    <div>
        <?php
        // upload form will process on init action

        if (!empty($_POST) && (!isset($_POST['cupriExport_form_nonce']) || !wp_verify_nonce($_POST['cupriExport_form_nonce'], 'cupriExport_form_nonce'))) {
            echo cupri_failed_msg('مقادیر ارسالی از منبع معتبری نیستند');

        } else {
            //any message to show?
            if (isset($_POST['cupri_export_msg']) && !empty($_POST['cupri_export_msg'])) {
                $cupri_export_msg = sanitize_text_field($_POST['cupri_export_msg']);
                echo '<div class="notice"><p>' . esc_html($cupri_export_msg) . '</p></div>';

            }
        }


        ?>
        <form action="" method="post">
            <?php
            wp_nonce_field('cupriExport_form_nonce', 'cupriExport_form_nonce');
            ?>
            <p>
                <strong>انتخاب بازه</strong> :<br>
                <select name="cupri_baze" id="cupri_baze">
                    <option value="0.1month">3 <?php echo __('Day') ?></option>
                    <option value="0.25month">7 <?php echo __('Day') ?></option>
                    <option value="0.5month">15 <?php echo __('Day') ?></option>
                    <option value="1month">1 <?php echo __('Month') ?></option>
                    <option value="2month">2 <?php echo __('Month') ?></option>
                    <option value="3month">3 <?php echo __('Month') ?></option>
                    <option value="6month">6 <?php echo __('Month') ?></option>
                    <option value="12month">12 <?php echo __('Month') ?></option>
                    <option value="24month">24 <?php echo __('Month') ?></option>
                </select>

            </p>
            <p>
                <button class="button button-primary"><?php echo __('Export'); ?></button>
            </p>
        </form>
    </div>
</div>
