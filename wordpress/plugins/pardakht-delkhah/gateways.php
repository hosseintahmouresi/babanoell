<?php
defined('ABSPATH') or die('No script kiddies please!');
?>
<div class="wrap">
    <h2></h2>
    <style type="text/css">
        .cupri-active-tab a {
            border-bottom: 1px solid green;
        }

        h2, h4, h5 {
            margin: 0;
        }

        .cupri_gateways {
            padding: 10px;
            margin: 5px;
        }

        .cupri_gateways .fields {
        }

        .ui-tabs .ui-tabs-nav li {
            float: right;
        }

        .ui-widget-content, .ui-widget-header {
            border: none !important;
            background: none !important;
        }

        .ui-tabs .ui-tabs-nav {
            border-bottom: 1px solid #ccc !important;
        }

        .ui-widget-header a {
            background: #e5e5e5;
        }

        .ui-corner-all, .ui-corner-top, .ui-corner-right, .ui-corner-tr {
            border: none !important;
        }

        .wp-person a:focus .gravatar, a:focus, a:focus .media-icon img {
            box-shadow: none !important;
        }
    </style>
    <?php
    if (isset($_POST['cupri_gateways']) && !empty($_POST['cupri_gateways']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'cupri_gateways_form')) {
        // do_action( 'cupri_gateways_form');
        foreach ($_POST['cupri_gateways'] as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $key_v => $value_v) {
                    if (isset($_POST['cupri_gateways'][$key][$value_v])) {
                        $_POST['cupri_gateways'][$key][$value_v] = sanitize_text_field($_POST['cupri_gateways'][$key][$value_v]);

                    }
                }

            } else {
                if (isset($_POST['cupri_gateways'][$key])) {
                    $_POST['cupri_gateways'][$key] = sanitize_text_field($_POST['cupri_gateways'][$key]);

                }
            }
        }
        $cupri_gateways = cupri_array_map_recursive('sanitize_text_field', $_POST['cupri_gateways']);
        update_option('cupri_gateways_settings', $cupri_gateways);
    }
    $cupri_gateways_settings = get_option('cupri_gateways_settings');
    $gateways = apply_filters('cupri_gateways', array());
    $currencies = apply_filters('cupri_currencies', array('Toman' => __('Toman', 'cupri'), 'Rial' => __('Rial', 'cupri')));
    ?>
    <h1><?php _e('Gateway Settings', 'cupri'); ?></h1>
    <hr>
    <form method="post" enctype="multipart/form-data">
        <?php
        wp_nonce_field('cupri_gateways_form');
        ?>
        <h2> :: <?php _e('Default Gateway', 'cupri'); ?> </h2>

        <p class="">
            <select name="cupri_gateways[default]">
                <?php
                foreach ($gateways as $g_id => $g_name) {
                    echo '<option ' . selected($cupri_gateways_settings['default'], $g_id, false) . ' value="' . esc_attr($g_id) . '">' . esc_html($g_name) . '</option>';
                }
                ?>
            </select>
        </p>
        <h2> :: <?php _e('Default Currency', 'cupri'); ?> <small>(<?php _e('currency', 'cupri');
                echo ' : ';
                echo cupri_get_currency() ?>)</small></h2>

        <p class="">
            <select name="cupri_gateways[currency]">
                <?php
                foreach ($currencies as $c_id => $c_name) {
                    echo '<option ' . selected($cupri_gateways_settings['currency'], $c_id, false) . ' value="' . esc_attr($c_id) . '">' . esc_html($c_name) . '</option>';
                }
                ?>
            </select>
        </p>
        <h2> :: <?php _e('Gateways', 'cupri'); ?> </h2>

        <?php

        // add paid menu
        $gateways['__private__paid'] = 'درگاه های دیگر';

        $tabs = '';
        $tabs_contents = '';
        $counter = 0;
        foreach ($gateways as $id => $name) {
            $counter++;
            $active_tab_class = ['tab-cupri-' . $id];
            if ($id == $cupri_gateways_settings['default']) {
                $active_tab_class[] = 'cupri-active-tab';
            }

            $tabs .= '<li data-tab-id="' . $counter . '" class="' . implode(' ', $active_tab_class) . '"><h4><a href="#tabs-' . $counter . '">  :: ' . $name . '</a></h4><li>';
            $settings = apply_filters('cupri_gateways_' . $id . '_settings', array());
            $tabs_contents .= '<div id="tabs-' . $counter . '">';
            $tabs_contents .= '<hr>';
            $tabs_contents .= '<hr><h3>' . $name . '</h3>';
            ob_start();
            do_action('cupri_gateways_' . $id . '_tabs_contents');
            $tabs_contents .= ob_get_clean();

            foreach ($settings as $s_id => $s_name) {
                $value = '';
                if (isset($cupri_gateways_settings[$id][$s_id])) {
                    $value = $cupri_gateways_settings[$id][$s_id];
                }
                $tabs_contents .= '<p class="fields"><label>';
                $tabs_contents .= '<strong>';
                $tabs_contents .= $s_name;
                $tabs_contents .= '</strong><br>';
                $tabs_contents .= '<input type="text" value="' . $value . '" name="cupri_gateways[' . $id . '][' . $s_id . ']">';
                $tabs_contents .= '</label></p>';
            }
            $tabs_contents .= '</div>';

        }
        ?>

        <?php
        echo '<div class="cupri_gateways">';
        ?>
        <div id="gateways_tabs">
            <ul>
                <?php
                echo wp_kses($tabs, ['strong' => [], 'ul' => [], 'div' => ['id' => []], 'p' => ['class' => []], 'label' => [], 'br' => [], 'input' => ['checked' => [], 'type' => [], 'value' => [], 'name' => []], 'hr' => [], 'h3' => [], 'h4' => [], 'li' => ['data-tab-id' => [], 'class' => []], 'a' => ['href' => []]]);
                ?>
            </ul>
            <?php
            echo wp_kses($tabs_contents, ['textarea' => ['name' => [], 'id' => [], '', 'cols' => [], 'rows' => []], 'strong' => [], 'ul' => [], 'div' => ['id' => []], 'p' => ['class' => []], 'label' => [], 'br' => [], 'input' => ['checked' => [], 'type' => [], 'value' => [], 'name' => []], 'hr' => [], 'h3' => [], 'h4' => [], 'li' => ['data-tab-id' => [], 'class' => []], 'a' => ['href' => []]]);
            ?>
        </div>
        <?php
        echo '</div>';
        ?>
        <button class="button-primary"><?php _e('Save'); ?></button>
    </form>

</div>
<?php wp_enqueue_script("jquery-ui-core"); ?>
<?php wp_enqueue_script("jquery-ui-tabs"); ?>
<script>
    jQuery("document").ready(function ($) {
        var $default_tab = 1;
        if ($('.cupri-active-tab').length) {
            $default_tab = $('.cupri-active-tab').attr('data-tab-id');
        }
        jQuery("#gateways_tabs").tabs(
            {
                hide: {effect: "explode", duration: 1000},
                show: {effect: "blind", duration: 800},
                active: $default_tab

            }
        );
    });
</script>
