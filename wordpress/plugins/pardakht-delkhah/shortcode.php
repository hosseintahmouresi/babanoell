<?php
defined('ABSPATH') or die('No script kiddies please!');
echo '<div id="cupri_form">';
$cupri_gateways_settings = get_option('cupri_gateways_settings');
$cupri_general = cupri_get_opt();
if (!isset($cupri_gateways_settings['default']) || empty($cupri_gateways_settings['default'])) {
    if (current_user_can('manage_options')) {
        _e('Please set the default gateway from admin', 'cupri');
        echo '  ';
        echo '<a href="' . admin_url('edit.php?post_type=cupri_pay&page=cupri-gateways') . '">' . __('Settings', 'cupri') . '</a>';
    } else {
        _e('No defualt gateway was set', 'cupri');

    }
    return;
}
?>
<?php
if ((isset($cupri_general['disable_default_style']) && $cupri_general['disable_default_style'] != 1) || !isset($cupri_general['disable_default_style'])) {
    ?>
    <style type="text/css">
        <?php
        $form_scheme = isset($cupri_general['form_scheme']) ? $cupri_general['form_scheme'] : 1;
        $schemes = [1,2,3,4];
        if(in_array($form_scheme,$schemes))
        {
            require_once cupri_dir.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'styles'.DIRECTORY_SEPARATOR.$form_scheme.'.php';
        }else{
        $form_scheme = 1;
        require_once cupri_dir.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'styles'.DIRECTORY_SEPARATOR.$form_scheme.'.php';

        }

        ?>
    </style>
    <?php
} /* end of disable_default_style switch check */
?>
    <script>
        jQuery(document).ready(function ($) {
            function cupri_CommaFormatted(amount) {
                return amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }

            $('#cupri_fprice').on('keyup', function (e) {
                $price_value = $(this).val();
                $cupri_fprice_with_comma = $('#cupri_fprice_with_comma');
                $cupri_fprice_with_comma.text(cupri_CommaFormatted($price_value));
            });

            $('#cupri_anonymously_pay').on('change', function (e) {
                if ($(this).is(':checked')) {
                    $('.cupri_input_wrapper input[required!=required]').not('#cupri_anonymously_pay').parents('.cupri_input_wrapper').slideUp();

                } else {
                    $('.cupri_input_wrapper input[required!=required]').not('#cupri_anonymously_pay').parents('.cupri_input_wrapper').slideDown();

                }

            });

            $('.cupri_predefined_prices').on('click', function () {
                $('#cupri_fprice').val($(this).attr('data-price'));
            });
        });
    </script>

<?php
/*
<script type="text/javascript">

	//this is ajax action that disabled and is beta
	jQuery(document).ready(function($) {
		var cupri_has_error = false;
		$('#cupri_submit').on('click' , function(e){
			// e.preventDefault();
			$('#cupri_submit_form input[required]').each(function() {
				$(this).css({borderBottom: '1 solid #eee'})
				if($.trim($(this).val())=='') {
					cupri_has_error = true;
					$(this).css({borderBottom: '0 solid #FF0000'}).animate({
						borderWidth: 1
					}, 100);
				}   
			});


			if(cupri_has_error) return false;

			var ajx_img = $('.cupri_ajax_img'),
			cupri_response_placeholder = $('.cupri_response_placeholder');
			ajx_img.slideDown();
			var _form_data = new FormData($('#cupri_submit_form')[0]);
			_form_data.append('action', 'cupri_action');

			$.ajax({
				url: '<?php echo add_query_arg(array('_nocacheplease'=> time()), admin_url('admin-ajax.php'));  ?>&nocachejs='+new Date().getTime(),
				type: 'POST',
				dataType: 'html',
				cache: false, // Prevent Browser Cache 
				processData: false,
				contentType: false,
				data: _form_data,
			})
			.done(function(data) {
				ajx_img.slideUp();
				cupri_response_placeholder.html(data);

			})
			.fail(function(data) {
				ajx_img.slideUp();
				cupri_response_placeholder.html(data);
			})
			.always(function(data) {
				ajx_img.slideUp();
				cupri_response_placeholder.html(data);
			});

		});
	});
</script>
*/ ?>
<?php
if (!defined('CUPRI_SCRIPTS_LOADED')) {

    wp_enqueue_style('cupri-user-css', cupri_url . '/assets/user.css');
    wp_enqueue_script('cupri-user-js', cupri_url . '/assets/user.js', array('jquery'));
    define('CUPRI_SCRIPTS_LOADED', true);

}
?>
<?php
/**
 * If Javascript is disabled so form works with below code
 */
if (isset($_POST['cupri_fprice']) && !empty($_POST['cupri_fprice'])) {
    define('DOING_AJAX', FALSE);
    $cupri = cupri::get_instance();
    $cupri->ajax();

}

$_cupri = get_option('_cupri', cupri_get_defaults_fields());

echo '<div class="cupri_row">';
echo '<div class="cupri_submit_form_wrapper col-xs-12 text-center">';
echo '<form method="post" id="cupri_submit_form" action="#cupri_form" >';
echo '<div class="cupri_top_form_devider"></div>';

foreach ($_cupri['type'] as $wc_cf_key => $wc_cf) {
    $value = '';
    $hasReadOnly = false;
    $hasRequired = false;
    $hasPlaceholder = false;


    $attr = [];
    // default value for price
    if ($_cupri['type'][$wc_cf_key] == 'price' && isset($_cupri['default'][$wc_cf_key])) {
        $value = $_cupri['default'][$wc_cf_key];
    }

    // default value for text  /  multi line text
    if (($_cupri['type'][$wc_cf_key] == 'text' || $_cupri['type'][$wc_cf_key] == 'multi_line_text') && isset($_cupri['text_default'][$wc_cf_key])) {
        $value = $_cupri['text_default'][$wc_cf_key];
    }


    // check default value from string query
    if (isset($_GET['cupri_f' . $wc_cf_key]) && cupri_get_opt('disable_readonly_predefined_values') != 1) {
        $value = sanitize_text_field($_GET['cupri_f' . $wc_cf_key]);
        // make readonly
        $hasReadOnly = true;
    }

    // readonly ?
    if (isset($_cupri['readonly']) && isset($_cupri['readonly'][$wc_cf_key]) && $_cupri['readonly'][$wc_cf_key] == 1) {
        $hasReadOnly = true;

    }


    //required ?
    if ($_cupri['type'][$wc_cf_key] == 'price' || (isset($_cupri['required'][$wc_cf_key]) && $_cupri['required'][$wc_cf_key] == 1)) {
        $hasRequired = true;
    }

    if (isset($_cupri['disable'][$wc_cf_key]) && $_cupri['disable'][$wc_cf_key] == 1) {
        continue;
    }


    echo '<div class="cupri_input_wrapper">';
    if ($_cupri['type'][$wc_cf_key] == 'paragraph') {
        /*Dont show title if the field type is paragraph,just print!*/
        echo '<label class="cupri_f' . esc_html($wc_cf_key) . '">';
    } else {
        echo '<label class="cupri_tbl cupri_f' . esc_html($wc_cf_key) . '">';
        echo '<span class="cupri_input_title">';
        echo '<span>' . esc_html($_cupri['name'][$wc_cf_key]);
        if ($hasRequired) {
            echo '<span style="color:red;font-weigth:bold;">*</span>';
        } else {
            echo ' <small style="color:green;font-weigth:bold;">اختیاری</small>';

        }
        echo '</span>';
        echo '</span>';

    }
    $hasPlaceholder = empty($_cupri['name'][$wc_cf_key]) ? false : $_cupri['name'][$wc_cf_key];
    $placeholder = $_cupri['name'][$wc_cf_key];

    switch ($_cupri['type'][$wc_cf_key]) {
        /**
         * Builtin Fields
         */
        case 'price':
            $has_pre_value_price = false;
            $has_pre_value_price_item = false;
            if (isset($_GET['cupri_f' . $wc_cf_key])) {
                $has_pre_value_price = true;
                $value = $has_pre_value_price_item = sanitize_text_field($_GET['cupri_f' . $wc_cf_key]);
                if ($wc_cf_key == 'price') {
                    /**
                     * حذف علایم جداسازی اعداد
                     */
                    $value = str_replace(array('/', '\\', ',', '،'), '', $value);
                }
                $value = (int)$value;
            }
            echo '<input ' . ($hasPlaceholder ? 'placeholder="' . esc_attr($placeholder) . '"' : '') . ' ' . ($hasRequired ? ' required="required" ' : '') . ' ' . ' ' . ($hasReadOnly ? ' readonly="readonly" ' : '') . ' ' . ' value="' . esc_attr($value) . '"  type="number" name="cupri_f' . esc_attr($wc_cf_key) . '" id="cupri_f' . esc_attr($wc_cf_key) . '">';
            echo ' <small class="cupri_input_title_for_price" style="color:green;font-weigth:bold;">(' . cupri_get_currency() . ')</small>';


            $predefined_prices = isset($cupri_general['predefined_prices']) ? $cupri_general['predefined_prices'] : '';
            $predefined_prices = explode(',', $predefined_prices);
            if (!empty($predefined_prices) && count($predefined_prices) > 0) {
                foreach ($predefined_prices as $predefined_price) {
                    if ($predefined_price > 0) {
                        echo '<span data-price="' . esc_attr($predefined_price) . '" class="cupri_predefined_prices">' . number_format($predefined_price) . ' <small>' . cupri_get_currency() . '</small></span>';
                    }
                }
            }
            echo '<span id="cupri_fprice_with_comma"></span>';

            /**
             * تیک حمایت به صورت ناشناس
             */
            if (isset($cupri_general['anonymously_pay_tick']) && $cupri_general['anonymously_pay_tick'] == 1) {
                echo '<label class="cupri_anonymously_pay_wrapper" for="cupri_anonymously_pay">';
                echo isset($cupri_general['anonymously_pay_text']) ? esc_html($cupri_general['anonymously_pay_text']) : 'حمایت به صورت ناشناس';
                echo '        <input id="cupri_anonymously_pay" type="checkbox" >';
                echo '</label>';
            }

            break;
        case 'mobile':
            echo '<input ' . ($hasPlaceholder ? 'placeholder="' . esc_attr($placeholder) . '"' : '') . ' ' . ($hasRequired ? ' required="required" ' : '') . ' ' . ' ' . ($hasReadOnly ? ' readonly="readonly" ' : '') . ' ' . ' value="' . esc_attr($value) . '" type="text" name="cupri_f' . esc_attr($wc_cf_key) . '" id="cupri_f' . esc_attr($wc_cf_key) . '">';

            break;
        case 'email':
            echo '<input  ' . ($hasPlaceholder ? 'placeholder="' . esc_attr($placeholder) . '"' : '') . ' ' . ($hasRequired ? ' required="required" ' : '') . ' ' . ' ' . ($hasReadOnly ? ' readonly="readonly" ' : '') . ' ' . ' value="' . esc_attr($value) . '" type="email" name="cupri_f' . esc_attr($wc_cf_key) . '" id="cupri_f' . esc_attr($wc_cf_key) . '">';

            break;
        /**
         * Other Fields added in admin
         */

        case 'text':
            echo '<input ' . ($hasPlaceholder ? 'placeholder="' . esc_attr($placeholder) . '"' : '') . ' ' . ($hasRequired ? ' required="required" ' : '') . ' ' . ' ' . ($hasReadOnly ? ' readonly="readonly" ' : '') . ' ' . ' value="' . esc_attr($value) . '"  type="text" name="cupri_f' . esc_attr($wc_cf_key) . '" id="cupri_f' . esc_attr($wc_cf_key) . '">';
            break;
        case 'multi_line_text':
            echo '<textarea ' . ($hasPlaceholder ? 'placeholder="' . esc_attr($placeholder) . '"' : '') . ' ' . ($hasRequired ? ' required="required" ' : '') . ' ' . ' ' . ($hasReadOnly ? ' readonly="readonly" ' : '') . ' ' . ' type="text" name="cupri_f' . esc_attr($wc_cf_key) . '" id="cupri_f' . esc_attr($wc_cf_key) . '">' . esc_textarea($value) . '</textarea>';
            break;
        case 'checkbox':
            echo '<input ' . ($hasPlaceholder ? 'placeholder="' . esc_attr($placeholder) . '"' : '') . ' ' . ($hasRequired ? ' required="required" ' : '') . ' ' . ' ' . ($hasReadOnly ? ' readonly="readonly" ' : '') . ' ' . ' value="' . esc_attr($value) . '" type="checkbox" name="cupri_f' . esc_attr($wc_cf_key) . '" value="1" id="cupri_f' . esc_attr($wc_cf_key) . '">';
            break;
        case 'paragraph':
            echo '<p class="cupri_full_centered cupri_f' . esc_attr($wc_cf_key) . '">' . esc_html($_cupri['paragraph_content'][$wc_cf_key]) . '</p>';
            break;
        case 'select':
            $has_selected = false;
            $has_selected_item = false;
            if (isset($_GET['cupri_f' . $wc_cf_key]) && in_array($_GET['cupri_f' . $wc_cf_key], $_cupri['combobox_choices'][$wc_cf_key])) {
                $has_selected = true;
                $has_selected_item = sanitize_text_field($_GET['cupri_f' . $wc_cf_key]);
            }
            echo '<select style="width:100%;" ' . ($hasPlaceholder ? 'placeholder="' . esc_attr($placeholder) . '"' : '') . ' ' . ($hasRequired ? ' required="required" ' : '') . ' ' . ' ' . ($hasReadOnly ? ' readonly="readonly" ' : '') . ' ' . ' name="cupri_f' . esc_attr($wc_cf_key) . '" id="cupri_f' . esc_attr($wc_cf_key) . '">';
            echo '<option>---' . __('select one', 'cupri') . '---</option>';
            foreach ($_cupri['combobox_choices'][$wc_cf_key] as $combobox_choice) {
                echo '<option ' . (($has_selected) ? selected($has_selected_item, $combobox_choice, false) : '') . ' value="' . esc_attr($combobox_choice) . '">' . esc_html($combobox_choice) . '</option>';
            }
            echo '</select>';
            break;
        default:
            echo '<input ' . ($hasPlaceholder ? 'placeholder="' . esc_attr($placeholder) . '"' : '') . ' ' . ($hasRequired ? ' required="required" ' : '') . ' ' . ' ' . ($hasReadOnly ? ' readonly="readonly" ' : '') . ' ' . ' type="text" name="cupri_f' . esc_attr($wc_cf_key) . '" id="cupri_f' . esc_attr($wc_cf_key) . '">';

    }

    echo '</label>';
    echo '<div class="cupri_clear"></div>';
    echo '</div>';
}

$submit_button_text = '<span class="heart">&hearts; </span> پرداخت ';
if (isset($cupri_general['submit_button_text']) && !empty($cupri_general['submit_button_text'])) {
    $submit_button_text = $cupri_general['submit_button_text'];
}
echo '<p class="cupri_submit_label">';
echo '<button class="cupri_full_centered" name="cupri_submit" id="cupri_submit">' . cupri_wp_kses($submit_button_text) . '<img width="7px" style="display:none;" class="cupri_ajax_img" src="' . cupri_url . '/assets/ajax-loader.gif"></button>';
echo '<p class="cupri_response_placeholder alert"></p>';
echo '</p>';
echo '</form>';
echo '</div>';

echo '</div>';

echo '</div>';


