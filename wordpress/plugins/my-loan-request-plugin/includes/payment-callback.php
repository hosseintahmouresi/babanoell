<?php
/**
 * Generic payment callback endpoint for future online payment gateways.
 *
 * This endpoint is intentionally provider-agnostic and does not mutate any
 * loan/payment state by itself. A future gateway integration can attach its
 * verification logic to the hooks exposed below while keeping the public
 * callback URL unchanged.
 *
 * @package MLR
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mlr_get_payment_callback_url')) {
    /**
     * Return the stable public callback URL used by future payment gateways.
     *
     * @return string
     */
    function mlr_get_payment_callback_url() {
        return rest_url('mlr/v1/payment-callback');
    }
}

if (!function_exists('mlr_register_payment_callback_route')) {
    /**
     * Register a public GET/POST endpoint. Payment providers must be able to
     * reach this route without a WordPress login, so the permission callback
     * intentionally allows anonymous requests. Security/verification belongs
     * to the concrete gateway handler that will be attached later.
     */
    function mlr_register_payment_callback_route() {
        register_rest_route('mlr/v1', '/payment-callback', [
            'methods'             => ['GET', 'POST'],
            'callback'            => 'mlr_payment_callback_handler',
            'permission_callback' => '__return_true',
        ]);
    }
}
add_action('rest_api_init', 'mlr_register_payment_callback_route');

if (!function_exists('mlr_payment_callback_handler')) {
    /**
     * Generic callback dispatcher.
     *
     * Until a payment gateway is integrated, this endpoint only confirms that
     * it is online. It deliberately does not log arbitrary payloads, change a
     * loan request, or mark a payment successful.
     *
     * Future integrations can use:
     *   do_action( 'mlr_payment_callback_received', $request );
     *   apply_filters( 'mlr_payment_callback_response', null, $request );
     *
     * A gateway handler should verify the callback server-to-server before
     * changing any financial state, then return a WP_REST_Response (or array)
     * through the response filter.
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response|WP_Error
     */
    function mlr_payment_callback_handler(WP_REST_Request $request) {
        /**
         * Fires whenever the generic payment callback URL receives a request.
         * No financial state is changed by the core callback implementation.
         *
         * @param WP_REST_Request $request Callback request.
         */
        do_action('mlr_payment_callback_received', $request);

        /**
         * Allow a future gateway implementation to completely handle the
         * callback while preserving this stable public URL.
         *
         * Return null to use the default readiness response, a WP_REST_Response
         * / WP_Error for full control, or an array for a normal JSON response.
         *
         * @param mixed           $response Custom response or null.
         * @param WP_REST_Request $request  Callback request.
         */
        $response = apply_filters('mlr_payment_callback_response', null, $request);

        if (is_wp_error($response) || $response instanceof WP_REST_Response) {
            return $response;
        }

        if (is_array($response)) {
            return rest_ensure_response($response);
        }

        return new WP_REST_Response([
            'ok'      => true,
            'status'  => 'ready',
            'message' => 'Payment callback endpoint is ready; no gateway handler is configured yet.',
        ], 200);
    }
}

if (!function_exists('mlr_render_payment_callback_settings_card')) {
    /**
     * Show the fixed callback URL on the existing API settings page.
     */
    function mlr_render_payment_callback_settings_card() {
        $callback_url = mlr_get_payment_callback_url();
        ?>
        <div class="mlr-settings-card" style="margin-top:16px;">
            <h2>Callback عمومی پرداخت آنلاین</h2>
            <p>این آدرس ثابت برای اتصال درگاه‌های پرداخت آنلاین آینده ساخته شده است. در حال حاضر فقط سلامت Endpoint را تأیید می‌کند و هیچ وضعیت مالی یا پرونده‌ای را تغییر نمی‌دهد.</p>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="mlr_payment_callback_url">Callback URL</label></th>
                    <td>
                        <input
                            type="url"
                            id="mlr_payment_callback_url"
                            value="<?php echo esc_attr($callback_url); ?>"
                            class="large-text code ltr"
                            readonly
                            onclick="this.select();"
                        />
                        <p class="description">همین آدرس را می‌توانید به درگاه به‌عنوان Callback / Return URL اعلام کنید. منطق Verify درگاه بعداً بدون تغییر این URL به افزونه اضافه می‌شود.</p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
}
add_action('mlr_api_settings_page_extra_sections', 'mlr_render_payment_callback_settings_card', 5);
