<?php
defined('ABSPATH') or die('No script kiddies please!');
$notes = array
(
    'درگاه پرداخت اینترنتی چیست و کدام را انتخاب کنیم؟' => '<a href="https://wp-master.ir/what-is-payment-gateway-and-which-one-should-we-choose/">برای مطالعه راهنمای انتخاب درگاه اینجا کلیک کنید</a>',
    __('Insert into posts or pages', 'cupri') => __("you can use [cupri] shortcode anywhere you want", 'cupri'),
    __('send pre selected value to selectable fields', 'cupri') => __("add your value to link of your custom payment page with this sample: http://yoursite.com/custom-pay/?cupri_fX=Y while X is your field number and Y is it's value , so when user open this link that field selected value filled with sent value", 'cupri'),
    __('send pre defined value to price field', 'cupri') => __("such as above just use <i>price</i> instead of X", 'cupri'),
    __('Special role in transaction review', 'cupri') => __("You can define a user and give it a payment management role so that it can track transactions.This user will only have access to the payments menu.", 'cupri'),
);
?>
<div class="wrap">
    <?php foreach ($notes as $title => $note): ?>
        <h3><?php echo esc_html($title); ?></h3>
        <p sytle=""><?php echo wp_kses($note, ['strong' => [], 'ul' => [], 'div' => ['id' => []], 'p' => ['class' => []], 'label' => [], 'br' => [], 'input' => ['checked' => [], 'type' => [], 'value' => [], 'name' => []], 'hr' => [], 'h3' => [], 'h4' => [], 'li' => ['data-tab-id' => [], 'class' => []], 'a' => ['href' => []]]);
            ?></p>
    <?php endforeach ?>
</div>