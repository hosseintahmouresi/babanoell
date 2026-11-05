<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
$source = file_get_contents(dirname(__DIR__) . '/includes/admin-pages/installment-plans.php');
$passed = 0; $failed = 0;
function t($name, $condition) { global $passed, $failed; if ($condition) { echo "PASS: $name\n"; $passed++; } else { echo "FAIL: $name\n"; $failed++; } }

t('action notices have dedicated non-validation bucket', strpos($source, '$action_notice_errors = [];') !== false);
t('redirect notices are GET-only', strpos($source, 'if ($request_method === \'GET\')') !== false);
t('delete notice uses action notice bucket', strpos($source, '$action_notice_errors[] = $action_error_messages[$action_error];') !== false);
$start = strpos($source, 'if ($request_method === \'GET\')');
$end = strpos($source, '// --- Form Submission Handling ---', $start ?: 0);
$noticeBlock = ($start !== false && $end !== false) ? substr($source, $start, $end - $start) : '';
t('stale delete notice cannot enter validation errors', $noticeBlock !== '' && strpos($noticeBlock, '$errors[]') === false);
t('plan edit posts to canonical URL', strpos($source, '<form method="post" action="<?php echo esc_url(mlr_installment_plans_admin_url()); ?>" id="mlr-plan-form"') !== false);
t('plan order posts to canonical URL', strpos($source, '<form method="post" action="<?php echo esc_url(mlr_installment_plans_admin_url()); ?>" id="mlr-plan-order-form"') !== false);
t('one-shot action params are removed from browser history', strpos($source, "window.history.replaceState") !== false && strpos($source, "'mlr_plan_action_error'") !== false);
t('used plans are still protected from physical deletion', strpos($source, "WHERE `installment_plan_id` = %d") !== false && strpos($source, "'plan_in_use'") !== false);
t('occupation mode persistence remains intact', strpos($source, "\$settings['occupation_documents_enabled'] = \$occupation_documents_enabled;") !== false && strpos($source, "\$settings['required_documents_by_occupation'] = \$required_documents_by_occupation;") !== false);

echo "RESULT: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
