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

span.cupri_predefined_prices {
background: #c3c5c1;
margin: auto 3px;
padding: 3px;
border-radius: 5px;
cursor: pointer;
transition: all 0.3s ease-in-out;
border: 1px solid #cbcfc7;
}
span.cupri_predefined_prices:hover {
background: #daddd6;
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
-moz-transition: all 0.3s ease-in-out;
-o-transition: all 0.3s ease-in-out;
-webkit-transition: all 0.3s ease-in-out;
transition: all 0.3s ease-in-out;
outline: none;
padding: auto 5px !important;
margin: 0 3px 10px 3px;
border: 1px solid #DDDDDD;
-moz-box-shadow: none !important;
-webkit-box-shadow: none !important;
box-shadow: none !important;
line-height: auto !important;
background: #fff !important;
border-radius: .25rem;
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
-moz-box-shadow: 0 0 5px <?php echo esc_html($cupri_general ['form_color']); ?>;
-webkit-box-shadow: 0 0 5px <?php echo esc_html($cupri_general ['form_color']); ?>;
box-shadow: 0 0 5px <?php echo esc_html($cupri_general ['form_color']); ?>;
/*padding: 3px 0px 3px 3px;*/
/*margin: 0 3px 10px 3px;*/
border: 1px solid <?php echo esc_html($cupri_general ['form_color']); ?> !important;
outline: none;
background: #fff;
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
background: #ededed;
}
.cupri_top_form_devider {
width: 30%;
height: 2px;
border-bottom-width: 1px;
text-align: center;
margin: auto;
border-bottom-color: <?php echo(isset($cupri_general ['form_color']) ? $cupri_general ['form_color'] : '#51cbee'); ?> !important;
position: absolute;
top: 0;
left: 0;
right: 0;
border-bottom-style: solid;
}