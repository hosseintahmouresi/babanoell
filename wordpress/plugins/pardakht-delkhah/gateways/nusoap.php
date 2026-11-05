<?php
if (!class_exists('nusoap_client')) {
    if (version_compare(PHP_VERSION, '8.0', '>=')) {
        include_once 'nusoap-php8.php';
    } elseif (version_compare(PHP_VERSION, '5.3', '>')) {
        include_once 'nusoap-php7.php';
    } else {
        include_once 'nusoap-php5.3.php';
    }
}
