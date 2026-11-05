<?php
defined('ABSPATH') or die('No script kiddies please!');

/**
 * The Class.
 */
class cupri_meta_box
{

    /**
     * Hook into the appropriate actions when the class is constructed.
     */
    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save'));
    }

    /**
     * Adds the meta box container.
     */
    public function add_meta_box($post_type)
    {
        // Limit meta box to certain post types.
        $post_types = array('cupri_pay');

        if (in_array($post_type, $post_types)) {
            add_meta_box(
                'details_meta_box_name',
                __('Payment Details', 'cupri'),
                array($this, 'render_meta_box_content'),
                $post_type,
                'advanced',
                'high'
            );
        }
    }

    /**
     * Save the meta when the post is saved.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function save($post_id)
    {

        /*
         * We need to verify this came from the our screen and with proper authorization,
         * because save_post can be triggered at other times.
         */

        // Check if our nonce is set.
        if (!isset($_POST['cupri_inner_custom_box_nonce'])) {
            return $post_id;
        }

        $nonce = $_POST['cupri_inner_custom_box_nonce'];

        // Verify that the nonce is valid.
        if (!wp_verify_nonce($nonce, 'cupri_inner_custom_box')) {
            return $post_id;
        }

        /*
         * If this is an autosave, our form has not been submitted,
         * so we don't want to do anything.
         */
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $post_id;
        }

        // Check the user's permissions.
        if ('cupri_pay' == $_POST['post_type']) {
            if (!current_user_can('edit_page', $post_id)) {
                return $post_id;
            }
        } else {
            if (!current_user_can('edit_post', $post_id)) {
                return $post_id;
            }
        }

        /* OK, it's safe for us to save the data now. */

        // Sanitize the user input.
//        $mydata = sanitize_text_field( $_POST['cupri_new_field'] );

        // Update the meta field.
//        update_post_meta( $post_id, '_my_meta_value_key', $mydata );
    }


    /**
     * Render Meta Box content.
     *
     * @param WP_Post $post The post object.
     */
    public function render_meta_box_content($post)
    {

        // Add an nonce field so we can check for it later.
        wp_nonce_field('cupri_inner_custom_box', 'cupri_inner_custom_box_nonce');

        // Use get_post_meta to retrieve an existing value from the database.
        $value = get_post_meta($post->ID, '_my_meta_value_key', true);

        // Display the form, using the current value.
        ?>
        <style>
            #post-body-content, .page-title-action {
                display: none;
            }

            /*hide title & add new button*/
        </style>

        <?php
        $order_id = $post->ID;
        $order_details = '';
        $price = get_post_meta($order_id, '_cupri_fprice', true);
        $res_code = get_post_meta($order_id, '_cupri_result_code', true);
        $status = '-';
        $get_post_status_object = get_post_status_object(get_post_status($order_id));
        if (is_object($get_post_status_object) && !is_wp_error($get_post_status_object)) {
            $status = $get_post_status_object->label;
        }

        $order_details .= '
        <div class="cupri_order_details_wrapper">
            <table class="cupri_order_details links-table">
                <tbody>
                <tr>
                    <td><strong>' . __('Price', 'cupri') . ':</strong> </td>
                    <td>' . $price . ' <small>(' . cupri_get_currency() . ')</small></td>
                </tr>
                <tr>
                    <td><strong>' . __('Payment Status', 'cupri') . ':</strong> </td>
                    <td>' . $status . ' </td>
                </tr>
                <tr>
                    <td><strong>' . __('Result Code', 'cupri') . ':</strong> </td>
                    <td>' . $res_code . ' </td>
                </tr>
                ';


        $new_columns = array();
        $_cupri = get_option('_cupri', cupri_get_defaults_fields());
        foreach ($_cupri['type'] as $wc_cf_key => $wc_cf) {
            $key = 'cupri_f' . $wc_cf_key;
            $key = '_' . $key;
            if ($key == '_cupri_fprice' || (isset($_cupri['disable'][$wc_cf_key]) && $_cupri['disable'][$wc_cf_key] == 1)) continue;
            $order_details .= '<tr>
                    <td><strong>' . $_cupri['name'][$wc_cf_key] . ':</strong> </td>
                    <td>' . str_replace(array('\r\n'), array('<br/>'), get_post_meta($order_id, $key, true)) . ' </td>
                </tr>';
        }


        // other details
        $others = [
            __('Gateway', 'cupri') => get_post_meta($order_id, '_cupri_gateway', true),
            __('Currency', 'cupri') => get_post_meta($order_id, '_cupri_currency', true),
        ];


        $details = get_post_meta($order_id, '_cupri_log', true);
        if (!is_array($details)) {
            $details = [];
        }
        $details = $others + $details;
        foreach ($details as $d_name => $d_value) {
            $order_details .= '<tr>
                    <td><strong>' . $d_name . ':</strong> </td>
                    <td>' . $d_value . ' </td>
                </tr>';
        }


        $order_details .= '
                </tbody>
                <tfoot>
                <tr class="tfooter">
                    <td colspan=2>' . get_the_date('Y/m/d - g:i A', $order_id) . '</td>
                </tr>
                </tfoot>

                ';


        $order_details .= '
            </table>
        </div>
        ';

        $_msg = '<div class="cupri_msg_wrapper"> ' . $order_details . '</div>';

        $cupri_general = cupri_get_opt();
        $messgae_email = $cupri_general['admin_email_format'];
        $messgae_email = cupri_replace_notification_variables($order_id, $messgae_email);

        $messgae_sms = $cupri_general['admin_sms_format'];
        $messgae_sms = cupri_replace_notification_variables($order_id, $messgae_sms);


        echo wpautop(wp_kses($_msg, ['strong' => [], 'ul' => [], 'div' => ['id' => []], 'p' => ['class' => []], 'label' => [], 'br' => [], 'input' => ['checked' => [], 'type' => [], 'value' => [], 'name' => []], 'hr' => [], 'h3' => [], 'h4' => [], 'li' => ['data-tab-id' => [], 'class' => []], 'a' => ['href' => []], 'table' => [], 'tbody' => [], 'tfoot' => [], 'tr' => ['class' => []], 'td' => ['colspan' => []]]));
        echo '<hr><h4>' . __('Email Template Preview', 'cupri') . ':</h4>';
        echo wpautop(wp_kses($messgae_email, ['strong' => [], 'ul' => [], 'div' => ['id' => []], 'p' => ['class' => []], 'label' => [], 'br' => [], 'input' => ['checked' => [], 'type' => [], 'value' => [], 'name' => []], 'hr' => [], 'h3' => [], 'h4' => [], 'li' => ['data-tab-id' => [], 'class' => []], 'a' => ['href' => []], 'table' => [], 'tbody' => [], 'tfoot' => [], 'tr' => ['class' => []], 'td' => ['colspan' => []]]));

        echo '<hr><h4>' . __('SMS Template Preview', 'cupri') . ':</h4>';
        echo wpautop(wp_kses($messgae_sms, ['strong' => [], 'ul' => [], 'div' => ['id' => []], 'p' => ['class' => []], 'label' => [], 'br' => [], 'input' => ['checked' => [], 'type' => [], 'value' => [], 'name' => []], 'hr' => [], 'h3' => [], 'h4' => [], 'li' => ['data-tab-id' => [], 'class' => []], 'a' => ['href' => []], 'table' => [], 'tbody' => [], 'tfoot' => [], 'tr' => ['class' => []], 'td' => ['colspan' => []]]));


    }
}