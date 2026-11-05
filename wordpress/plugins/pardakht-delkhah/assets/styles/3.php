.cupri_ajax_img {
display: none;
width: 7px !important;
outline: none !important;
border: none !important;
padding: 0 !important;
vertical-align: middle;
background: none !important;
}

#cupri_submit_form label{
display: inline-block !important;
width: 100% !important;
}

.cupri_response_placeholder ul li {
list-style: none;
color: #000;
font-style: unset;
background: yellow;
}

.cupri-errors {
background: yellow;
color: #000;
}

#cupri_submit_form, .cupri-errors {
width: 270px;
margin: auto;
text-align: center;
padding: 15px;
border-radius: 5px; /*box-shadow: 0px 1px 18px #ededed;*/
}

#cupri_submit_form input[type="number"],
#cupri_submit_form input[type="number"] {
-webkit-appearance: none !important;
appearance: none !important;
-moz-appearance: textfield !important;
}

#cupri_submit_form ul {
width: 100% !important;
padding: 0 !important;
margin: 0 !important;
}

#cupri_submit_form input[type=text], #cupri_submit_form select, #cupri_submit_form input[type=number], #cupri_submit_form input[type=email], #cupri_submit_form textarea {
width: 100%;
height: calc(2.5em + .75rem + 2px);
border: none !important;
border-bottom: 1px solid rgba(0, 0, 0, 0.1) !important;
padding: 0 !important;
border-color: none !important;
background: none !important;

}
/*
#cupri_submit_form input[type=number] {
width: 96%;
padding: auto 4px !important;
}*/

#cupri_submit_form select {
padding: 4px !important;
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

#cupri_submit_form #cupri_submit:focus {
outline: 0 !important;
}

#cupri_submit_form #cupri_submit span.heart {
transition: all ease .3s;
}

#cupri_submit_form #cupri_submit:hover span.heart {
color: red;
}


span#cupri_fprice_with_comma {
min-height: 20px;
display: block;
direction: ltr;
font-family: monospace;
font-size: 9pt;
}
.cupri_input_title > span , .cupri_input_title_for_price {
text-align: right !important;
float: right;
margin-right: 7px;
font-weight: bold !important;
}
form#cupri_submit_form {
width: 70% !important;
}
.cupri_top_form_devider {

}

.cupri_input_wrapper input{box-shadow:none !important;}




