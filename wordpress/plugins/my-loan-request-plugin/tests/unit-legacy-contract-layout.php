<?php
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
if (!function_exists('add_action')) { function add_action(...$args) {} }
require_once dirname(__DIR__) . '/includes/loan-contract-signing.php';

$fail = function($msg) { fwrite(STDERR, "FAIL: {$msg}\n"); exit(1); };
$pass = function($msg) { echo "PASS: {$msg}\n"; };

$broken = '<div class="mlr-contract-document mlr-hami-plus-contract">' . "\n"
    . '<div class="mlr-contract-document-head">' . "\n"
    . '<div class="mlr-contract-meta-row"><strong>تاریخ:</strong> 1405/07/09' . "\n" . '<strong>شماره:</strong> 1240</div>' . "\n"
    . '<div class="mlr-contract-parties-bar">لیزینگ: الف' . "\n" . 'پلتفرم: ب' . "\n" . 'خریدار: ج</div>' . "\n"
    . '</div>' . "\n"
    . '<h2>ماده 1</h2>' . "\n" . 'متن اول' . "\n\n"
    . '<strong>تبصره:</strong> متن دوم' . "\n"
    . '<h2>ماده 2</h2>' . "\n" . 'متن سوم' . "\n"
    . '<h2>ماده 3</h2>' . "\n" . 'متن چهارم' . "\n"
    . '<h2>ماده 4</h2>' . "\n" . 'متن پنجم' . "\n"
    . '<h2>ماده 5</h2>' . "\n" . 'متن ششم' . "\n"
    . '</div>';

$repaired = mlr_repair_legacy_mixed_contract_markup_for_display($broken);
if ($repaired === $broken) $fail('mixed legacy snapshot was not repaired');
if (substr_count($repaired, 'mlr-contract-legacy-paragraph') < 6) $fail('loose legal paragraphs were not restored');
if (strpos($repaired, 'mlr-contract-meta-row"><span>') === false) $fail('meta row spans were not restored');
if (strpos($repaired, 'mlr-contract-parties-bar"><span>') === false) $fail('party row spans were not restored');
if (strpos($repaired, 'mlr-contract-legacy-layout-repaired') === false) $fail('repair marker class missing');
$pass('mixed legacy layout restored');

$structured = '<div class="mlr-contract-document">' . str_repeat('<h2>ماده</h2><p>متن</p>', 10) . '</div>';
if (mlr_repair_legacy_mixed_contract_markup_for_display($structured) !== $structured) $fail('structured contract changed');
$pass('structured contract remains byte-identical');

$plain_old = '<p>قرارداد قدیمی</p><br><p>متن دیگر</p>';
if (mlr_repair_legacy_mixed_contract_markup_for_display($plain_old) !== $plain_old) $fail('other legacy contract family changed');
$pass('other contract family remains untouched');
