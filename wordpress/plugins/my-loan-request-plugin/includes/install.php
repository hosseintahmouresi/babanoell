<?php
/**
 * Backward-compatible installer entry point.
 *
 * Older builds shipped a second independent schema definition in this file.
 * Keeping two schema sources caused drift, so this wrapper now delegates to the
 * canonical installer defined by the main plugin file.
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mlr_install')) {
    function mlr_install() {
        if (function_exists('mlr_install_and_update')) {
            mlr_install_and_update();
        }
    }
}
