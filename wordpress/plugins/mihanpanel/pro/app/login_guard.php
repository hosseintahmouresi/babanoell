<?php

namespace mihanpanel\pro\app;

class login_guard
{
    static function init()
    {
        // check wrong code in sms login
        add_action('mwpl_login/sms_login/wrong_dynamic_code_value', ['\mihanpanel\app\login_guard', 'handleFailedAttemptsLoginProcessInLoginForm']);
        add_action('mwpl_login/sms_login/wrong_dynamic_code_value', ['\mihanpanel\app\login_guard', 'checkFailedAttemptsCountInLoginProcess']);
        add_action('mwpl_sms_login_start_process_response', ['\mihanpanel\app\login_guard', 'checkFailedAttemptsCountInLoginProcess']);

        // check wrong password or code in smart login
        add_action('mwpl_login/smart_login/wrong_username', ['\mihanpanel\app\login_guard', 'handleFailedAttemptsLoginProcessInLoginForm']);
        add_action('mwpl_login/smart_login/no_user_found', ['\mihanpanel\app\login_guard', 'handleFailedAttemptsLoginProcessInLoginForm']);
        add_action('mwpl_login/smart_login/wrong_password', ['\mihanpanel\app\login_guard', 'handleFailedAttemptsLoginProcessInLoginForm']);
        add_action('mwpl_login/smart_login/wrong_dynamic_code_value', ['\mihanpanel\app\login_guard', 'handleFailedAttemptsLoginProcessInLoginForm']);
        
        add_action('mwpl_login/smart_login/wrong_username', ['\mihanpanel\app\login_guard', 'checkFailedAttemptsCountInLoginProcess']);
        add_action('mwpl_login/smart_login/no_user_found', ['\mihanpanel\app\login_guard', 'checkFailedAttemptsCountInLoginProcess']);
        add_action('mwpl_login/smart_login/wrong_password', ['\mihanpanel\app\login_guard', 'checkFailedAttemptsCountInLoginProcess']);
        add_action('mwpl_login/smart_login/wrong_dynamic_code_value', ['\mihanpanel\app\login_guard', 'checkFailedAttemptsCountInLoginProcess']);

        // start of username check process
        add_action('mwpl_smart_login_start_process_response', ['\mihanpanel\app\login_guard', 'checkFailedAttemptsCountInLoginProcess']);
        // start of check code process for login
        add_action('mwpl_smart_login_start_send_dynamic_code_process_response', ['\mihanpanel\app\login_guard', 'checkFailedAttemptsCountInLoginProcess']);
        // start of check code process for register
        add_action('mwpl_smart_login_start_dynamic_code_process_response', ['\mihanpanel\app\login_guard', 'checkFailedAttemptsCountInLoginProcess']);
    }
}
