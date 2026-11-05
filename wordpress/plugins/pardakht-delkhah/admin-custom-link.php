<?php
defined('ABSPATH') or die('No script kiddies please!');
?>
<div class="wrap">
    <h2><?= _e('Custom link','cupri'); ?></h2>
    <hr>
    <div>
        <p> با وارد کردن آدرس صفحه پرداخت و مبلغ مد نظر لینک اختصاصی برای کاربر بسازید،کاربر با وارد شدن توسط آن لینک می تواند مبلغی که برایش مشخص کرده اید پرداخت کند  </p>
        <p>آدرس صفحه پرداخت و مبلغ را وارد کنید تا لینک اختصاصی برای شما ساخته شود</p>

        <p>
            <strong>آدرس صفحه پرداخت:</strong>
            <br>
            <input type="text" id="cupri_link">
        </p>
        <p>
            <strong>مبلغ دلخواه:</strong>
            <br>
            <input type="number" id="cupri_price">
        </p>
        <input id="cupri_link_result" type="text" readonly>

        <p>
            <button class="button button-primary" id="cupri_create_link">ساخت لینک</button>
        </p>
    </div>
</div>
