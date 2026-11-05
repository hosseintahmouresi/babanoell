<?php
namespace mihanpanel\pro\app;
class email
{
    static function filterDynamicCodeEmailContent($code, $content)
    {
        return str_replace('[[code]]', $code, $content);
    }
    static function getFilteredDynamicCodeEmailContent($code)
    {
        $content = options::get_dynamic_code_email_content();
        return sprintf('<pre>%s</pre>', self::filterDynamicCodeEmailContent($code, $content));
    }
}