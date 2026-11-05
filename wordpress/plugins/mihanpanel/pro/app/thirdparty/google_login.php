<?php

namespace mihanpanel\pro\app\thirdparty;

use mihanpanel\app\login;
use mihanpanel\app\notice;
use mihanpanel\app\session;
use mihanpanel\pro\app\assets;
use mihanpanel\pro\app\options;
use mihanpanel\pro\app\views;

class google_login
{
    static function init()
    {
        // goolge login button
        add_action('mwpl_login_form_after_submit_button', [__CLASS__, 'addGoogleLoginButton']);

        add_action('mwpl_login_enqueue_assets', [__CLASS__, 'enqueueAssets']);

        // ajax
        add_action('wp_ajax_nopriv_mwpl_google_login_init_request', [__CLASS__, 'initRequest']);

        // add_action('mwpl_login_form_after_start_form', [__CLASS__, 'handleQueryStringStateMessages']);
        add_filter('mwpl_login_form_messages', [__CLASS__, 'filterLoginFormMessages']);

        add_action('login_init', [__CLASS__, 'handleGoogleLoginCallbackProcess']);
    }

    static function filterLoginFormMessages($messages)
    {

        if($sessionMessages = session::get('login_form_notices'))
        {
            $messages[] = [
                'type' => $sessionMessages['type'],
                'message' => $sessionMessages['msg'],
            ];
            session::unset('login_form_notices');
        }
        return $messages;
    }
    static function enqueueAssets()
    {
        assets::enqueue_style('google_login', assets::get_css_url('user/google_login'));
        assets::enqueue_script('google_login', assets::get_js_url('user/google_login'));
    }
    static function addGoogleLoginButton()
    {
?>

        <div class="mwpl-input-item">
            <a href="#" id="google_login_btn" class="mwpl-btn-behavior mwpl-google-login-button" nonce="<?php echo esc_attr(wp_create_nonce('mwpl_google_login'))?>">
                <span class="mwpl-google-button-text"><?php echo esc_html(options::getLoginWithGoogleBtnText()) ?></span>
                <span class="mwpl-google-icon"><img src="<?php echo \mihanpanel\pro\app\assets::get_img_url('google-logo', 'svg')?>" alt="google-icon"></span>
            </a>
        </div>

<?php
    }

    static function handleGoogleLoginCallbackProcess()
    {
        if (isset($_GET['google_login'])) {
            $clientID = options::getLoginWithGoogleClientID();
            $clientSecret = options::getLoginWithGoogleClientSecret();

            $redirectURI = add_query_arg('google_login', '1', wp_login_url());

            $code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : false;

            // Exchange code for access token
            $tokenURL = 'https://oauth2.googleapis.com/token';
            $tokenParams = array(
                'code' => $code,
                'client_id' => $clientID,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectURI,
                'grant_type' => 'authorization_code',
            );

            $response = wp_remote_post($tokenURL, array(
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'body' => $tokenParams,
            ));

            if (is_wp_error($response)) {
                // Handle error
                $notice = [
                    'type' => 'error',
                    'msg' => $response->get_error_message(),
                ];
                session::store('login_form_notices', $notice);
                wp_redirect(\mihanpanel\app\options::getMihanPanelLoginUrl());
                exit;
            }


            $body = json_decode(wp_remote_retrieve_body($response), true);
            $accessToken = isset($body['access_token']) ? $body['access_token'] : false;

            if(!$accessToken)
            {
                $notice = [
                    'type' => 'error',
                    // 'msg' => __('Sorry, missing access token', 'mihanpanel'),
                    'msg' => $body['error_description'],
                ];
                session::store('login_form_notices', $notice);
                wp_redirect(\mihanpanel\app\options::getMihanPanelLoginUrl());
                exit;
            }

            

            // Fetch user info
            $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
            $userInfoResponse = wp_remote_get(add_query_arg('access_token', $accessToken, $userInfoUrl));

            
            
            if (is_wp_error($userInfoResponse)) {
                // Handle error
                $notice = [
                    'type' => 'error',
                    'msg' => $userInfoResponse->get_error_message(),
                ];
                session::store('login_form_notices', $notice);
                wp_redirect(\mihanpanel\app\options::getMihanPanelLoginUrl());
                exit;
            }

            $userInfo = json_decode(wp_remote_retrieve_body($userInfoResponse), true);

            // Process user info (create/login user)

            // get user by email
            $email = isset($userInfo['email']) ? $userInfo['email'] : false;
            $wpUser = $email ? get_user_by('email', $email) : false;
            
            if(!$wpUser)
            {
                // if not exists => create new user and do login
                $username = sanitize_user(strtolower(str_replace(' ', '', strstr($email, '@', 1))));
                $userData = [
                    'user_login' => $username,
                    'user_email' => $email,
                    'user_pass' => wp_generate_password(),
                ];
                $userID = wp_insert_user($userData);
            }else{
                // get user data
                $userID = $wpUser->ID;
            }
            // doLogin
            $afterLoginRedirectUrl = apply_filters('mwpl_after_login_redirect_url', \mihanpanel\app\options::get_panel_url(), $userID);
            login::doLogin($userID, true, $afterLoginRedirectUrl);
            wp_redirect($afterLoginRedirectUrl);
            exit;
        }
    }

    static function initRequest()
    {
        check_ajax_referer('mwpl_google_login', 'nonce');
        $res['code'] = 400;


        $clientID = options::getLoginWithGoogleClientID();

        
        $redirectURI = add_query_arg('google_login', '1', wp_login_url());

        $res['redirect'] = $redirectURI;

        $authURL = 'https://accounts.google.com/o/oauth2/v2/auth';
        $params = array(
            'client_id' => $clientID,
            'redirect_uri' => $redirectURI,
            'response_type' => 'code',
            'scope' => 'email profile',
            'access_type' => 'online',
        );

        $res['auth_url'] = add_query_arg($params, $authURL);
        $res['status'] = 200;
        $res['msg'] = __('Authentication is in progress', 'mihanpanel');

        die(json_encode($res));



        // http://localhost/wp/login?google_login=1&code=4/0ATx3LY41NBMuIxo4UzD5fWpi6uCjwhobHYUAx4rodKXz6vRTbHEbxI3944bm7T7D05xUJw&scope=email+profile+openid+https://www.googleapis.com/auth/userinfo.profile+https://www.googleapis.com/auth/userinfo.email&authuser=0&prompt=consent
    }
}
