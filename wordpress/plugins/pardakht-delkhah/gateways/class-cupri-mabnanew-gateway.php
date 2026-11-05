<?php
defined('ABSPATH') or die('No script kiddies please!');

class cupri_pas_gateway extends cupri_abstract_gateway
{
    static protected $instance = null;

    function add_settings($settings)
    {
        $settings['terminal'] = 'terminal';
        return $settings;
    }

    function start($payment_data)
    {
        $order_id = $payment_data['order_id'];
        //create unique order id and prevent duplicate order id error
        $rand_order_id = $order_id . mt_rand(11111, 99999);

        $this->add_meta($order_id, 'rand_order_id', $rand_order_id);

        $price = $payment_data['price'] * 10; // this gateway based on rial
        $callback_url = add_query_arg(array('order_id' => $order_id), $this->callback_url);

        $terminal = trim($this->settings['terminal']); //Required

        $params = 'terminalID=' . $terminal . '&Amount=' . $price . '&callbackURL=' . urlencode($callback_url) . '&invoiceID=' . $rand_order_id;
//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_URL, 'https://sepehr.shaparak.ir:8081/V1/PeymentApi/GetToken');
//        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//        $res = curl_exec($ch);
//        curl_close($ch);

        $res = $this->post_data('https://sepehr.shaparak.ir:8081/V1/PeymentApi/GetToken', $params);

        if ($res) {
            $res = (object)$res;
        }

        if (!$res || empty($res->Accesstoken)) {
            $msg = 'خطا در اتصال';
            if (isset($res->Status)) {
                $msg .= "\n کد خطا : " . $res->Status;
                if ($res->Status == '-2') {
                    $msg .= "\n" . "IP شما مطابقت ندارد یا اینکه پورت خروجی ۸۰۸۱ روی هاست شما بسته است.";
                }
            }
            echo cupri_failed_msg($msg, $order_id);
            exit;
        } else {
            if (isset($res->Status) && isset($res->Status)) {
                //ok , redirect
                echo cupri_success_msg('در حال انتقال به بانک...');

                echo '<center><form id="pardakht_delkhah_sepehr2" action="https://sepehr.shaparak.ir:8080" method="POST">
                <input type="hidden" id="TerminalID" name="TerminalID" value="' . $terminal . '">
                <input type="hidden" id="getMethod" name="getMethod" value="1">
                <input type="hidden" id="token" name="token" value="' . $res->Accesstoken . '">
                <input type="submit" value="در صورت عدم هدایت به درگاه کلیک کنید" class="submit" />
                </form><center><script>
			window.onload=function(){document.getElementById("pardakht_delkhah_sepehr2").submit();}; </script>';
                return;
            } else {
                $msg = 'خطا در  ساخت توکن';
                echo cupri_failed_msg($msg, $order_id);
                return;
            }
        }


    }

    function end($payment_data)
    {
        $order_id = sanitize_text_field($_REQUEST['order_id']);
        $terminal = $this->settings['terminal']; //Required
        $Amount = $amount = $this->get_price($order_id);
        $Amount = $Amount * 10; // convert to toman
        $digitalreceipt = isset($_REQUEST['digitalreceipt']) ? sanitize_text_field($_REQUEST['digitalreceipt']) : '';

        if (isset($_REQUEST['respcode']) && $_REQUEST['respcode'] == '0') {
            if ($this->digitalreceipt_is_valid($digitalreceipt)) {
                $params = 'digitalreceipt=' . $digitalreceipt . '&Tid=' . $terminal;
//                $ch = curl_init();
//                curl_setopt($ch, CURLOPT_URL, 'https://sepehr.shaparak.ir:8081/V1/PeymentApi/Advice');
//                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
//                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
//                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//                $res = curl_exec($ch);
//                curl_close($ch);
//                $result = json_decode($res, true);
                $result = $this->post_data('https://sepehr.shaparak.ir:8081/V1/PeymentApi/Advice', $params);
                if (!$result) {
                    $msg = 'خطا در اتصال';
                    echo cupri_failed_msg($msg, $order_id);
                    return;
                }
                if ($result) {
                    $result = (array)$result;
                }
                if (isset($result['Status']) && strtoupper($result['Status']) == 'OK') {

                    if (floatval($result['ReturnId']) == floatval($Amount)) {
                        //success
                        //save $digitalreceipt for prevent duplicate use
                        update_post_meta($order_id, '_sepehr2_digitalreceipt', $digitalreceipt);

                        $referenceId = $digitalreceipt;

                        $this->msg['message'] = "پرداخت شما با موفقیت انجام شد<br/> کد ارجاع : $referenceId";
                        $this->msg['class'] = 'success';
                        $this->success($order_id);
                        $this->set_res_code($order_id, $referenceId);
                        echo cupri_success_msg($this->msg['message'], $order_id);
                        return;
                    } else {
                        $res = 'مبلغ واریز با قیمت محصول برابر نیست ، مبلغ واریزی :' . $result['ReturnId'];
                    }

                } else {
                    switch ($result['ReturnId']) {
                        case '-1':
                            $res = 'تراکنش پیدا نشد';
                            break;
                        case '-2':
                            $res = 'تراکنش قبلا Reverse شده است';
                            break;
                        case '-3':
                            $res = 'خطا عمومی';
                            break;
                        case '-4':
                            $res = 'امکان انجام درخواست برای این تراکنش وجود ندارد';
                            break;
                        case '-5':
                            $res = 'آدرس IP پذیرنده نامعتبر است';
                            break;
                        default:
                            $res = 'خطای ناشناس : ' . $result['ReturnId'];
                            break;

                    }
                }
            } else {
                $res = 'رسید قبلا استفاده شده است';
            }
        } else {
            $res = 'برگشت نا موفق از درگاه';
        }
        $this->msg['message'] = $res;
        //failed
        $this->failed($order_id);
        echo cupri_failed_msg($this->msg['message'], $order_id);

    }

    function redirect_post($url, array $data)
    {

        echo '<form name="redirectpost" id="redirectpost" method="post" action="' . esc_url($url) . '">';

        if (!is_null($data)) {
            foreach ($data as $k => $v) {
                echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr($v) . '"> ';
            }
        }

        echo ' </form><div id="main">
                 <script type="text/javascript">

                                document.getElementById("redirectpost").submit();

                        </script>
                    </body>
                    </html>';

        exit;
    }

    public function digitalreceipt_is_valid($digitalreceipt)
    {
        // new keys
        $meta_key = '_sepehr2_digitalreceipt';
        $meta_value = $digitalreceipt;
        $args = array(
            'post_type' => 'cupri_pay',
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => $meta_key,
                    'value' => $meta_value,
                    'compare' => '=',
                ),
            ),
        );
        $query = new WP_Query($args);
        if ($query->have_posts()) {
            return false;

        }
        // old mabna key
        $meta_key = '_mabna2_digitalreceipt';
        $meta_value = $digitalreceipt;
        $args = array(
            'post_type' => 'cupri_pay',
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => $meta_key,
                    'value' => $meta_value,
                    'compare' => '=',
                ),
            ),
        );
        $query = new WP_Query($args);
        if ($query->have_posts()) {
            return false;

        }

        return true;
    }
}

cupri_pas_gateway::get_instance('mabnanew', 'پرداخت الکترونیک سپهر');
