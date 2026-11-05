<?php
defined('ABSPATH') or die('No script kiddies please!');

class cupri_payir_gateway extends cupri_abstract_gateway
{
    protected static $instance = null;

    public function add_settings($settings)
    {
        $settings['api'] = 'کد api';
        return $settings;
    }

    public function start($payment_data)
    {
        $order_id = $payment_data['order_id'];
        $price = $payment_data['price'];
        $callback_url = add_query_arg(array('order_id' => $order_id), $this->callback_url);

        $api = $this->settings['api'];
        $amount = $price * 10; // To rial
        $redirect = urlencode($callback_url);
        $result = $this->send($api, $amount, $redirect);
        $result = json_decode($result);
        if ($result->status && isset($result->token)) {
            $go = "https://pay.ir/pg/$result->token";
            echo cupri_success_msg('در حال انتقال به بانک...');
            echo '<script>window.location.href="' . esc_url($go) . '";</script>';
        } else {
            echo cupri_failed_msg($result->errorMessage);
        }

    }

    public function end($payment_data)
    {
        $order_id = sanitize_text_field($_REQUEST['order_id']);
        $api = $this->settings['api'];
        $Amount = $this->get_price($order_id);

        $transId = sanitize_text_field($_POST['transId']);
        $result = $this->verify($api, $transId);
        $result = json_decode($result);
        if (is_object($result) && $result->status == 1) {
            $this->success($order_id);
            $this->set_res_code($order_id, $transId);
            echo cupri_success_msg('پرداخت شما با موفقیت انجام شد.با تشکر. کد رهگیری:' . $transId, $order_id);

        } else {
            $this->failed($order_id);
            echo cupri_failed_msg('در انجام تراکنش مشکلی رخ داده است،لطفا مجددا تلاش کنید.', $order_id);

        }

    }

    public function send($api, $amount, $redirect)
    {
        $test = false;
        if (empty($api) || $api == 'test') {
            $test = true;
            $api = 'test';
        }
        $url = 'https://pay.ir/pg/send';
        if ($test) {
            $url = 'https://pay.ir/pg/test/send';
        }
        // $ch = curl_init();
        // curl_setopt($ch, CURLOPT_URL, 'https://pay.ir/payment/send');
        // curl_setopt($ch, CURLOPT_POSTFIELDS, "api=$api&amount=$amount&redirect=$redirect");
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // $res = curl_exec($ch);
        // curl_close($ch);

        $fields = array(
            'api' => $api,
            'amount' => $amount,
            'redirect' => $redirect,
        );
        $args = array(
            'method' => 'POST',
            // 'httpversion' => '1.0',
            'timeout' => 20,
            'sslverify' => false,
            'body' => ($fields)
        );

        $response = wp_remote_post($url, $args);
        $res = $response['body'];
        return $res;
    }

    public function verify($api, $transId)
    {
        $test = false;
        if (empty($api) || $api == 'test') {
            $test = true;
            $api = 'test';
        }
        $url = 'https://pay.ir/pg/verify';
        if ($test) {
            $url = 'https://pay.ir/pg/test/verify';
        }
        // $ch = curl_init();
        // curl_setopt($ch, CURLOPT_URL, 'https://pay.ir/payment/verify');
        // curl_setopt($ch, CURLOPT_POSTFIELDS, "api=$api&transId=$transId");
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // $res = curl_exec($ch);
        // curl_close($ch);
        $fields = array(
            'api' => $api,
            'transId' => $transId
        );
        $args = array(
            'method' => 'POST',
            'timeout' => 20,
            // 'httpversion' => '1.0',
            'sslverify' => false,
            'body' => ($fields)
        );
        $response = wp_remote_post($url, $args);
        if (is_wp_error($response)) {
            return false;
        }
        $res = $response['body'];
        return $res;
    }

}

cupri_payir_gateway::get_instance('payir', 'pay.ir');


