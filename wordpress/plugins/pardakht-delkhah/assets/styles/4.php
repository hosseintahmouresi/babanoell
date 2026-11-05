#cupri_submit_form input[type=text]:focus, #cupri_submit_form input[type=number]:focus, #cupri_submit_form input[type=email]:focus, #cupri_submit_form textarea:focus {
-moz-box-shadow: 0 0 5px <?php echo esc_html($cupri_general ['form_color']); ?>;
-webkit-box-shadow: 0 0 5px <?php echo esc_html($cupri_general ['form_color']); ?>;
box-shadow: 0 0 5px <?php echo esc_html($cupri_general ['form_color']); ?>;
/*padding: 3px 0px 3px 3px;*/
/*margin: 0 3px 10px 3px;*/
border: 1px solid <?php echo esc_html($cupri_general ['form_color']); ?> !important;
outline: none;
background: #fff;
}


#cupri_submit_form input[type=text]:focus, #cupri_submit_form input[type=number]:focus, #cupri_submit_form input[type=email]:focus, #cupri_submit_form textarea:focus {
border:none !important;
box-shadow:none !important;
border-bottom: 1px solid <?php echo esc_html($cupri_general ['form_color']); ?> !important;
outline: none;
}

#cupri_submit_form .cupri_clear {
clear: both;
display: block;
}

#cupri_submit_form #cupri_submit {
outline: 0 !important;
border: 1px solid #ededed;
padding: 5px 10px;
font-size: 1em;
background: <?php echo(isset($cupri_general ['form_color']) ? esc_html($cupri_general ['form_color']) : '#51cbee'); ?>;
margin: auto;
display: block;
border-radius: 5px;
}




.cupri_input_wrapper {
display: block;
width: 100% !important;
background: #ededed;
padding: 10px;
margin: 3px;
}


.cupri_input_title {
min-width: 120px;
display: inline-block;
}


.cupri_submit_label {
text-align: center;
}
.cupri_predefined_prices {
background: #d4d4d4;
}

.cupri_anonymously_pay_wrapper {
text-align: center;
margin: 5px 0;
}

.cupri_input_wrapper input {
width: 100%;
}