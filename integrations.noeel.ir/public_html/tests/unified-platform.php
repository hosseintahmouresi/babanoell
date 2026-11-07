<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/bootstrap.php';
$root=dirname(__DIR__);
$success=0;
$assert=function(bool $ok,string $name)use(&$success):void{if(!$ok)throw new RuntimeException($name);echo "[PASS] $name\n";$success++;};
$v=require $root.'/config/version.php';
$assert(version_compare((string)($v['version']??'0'),'1.2.0','>=')&&($v['service']??null)==='integrations','unified version');
$ui=\Babanoell\Integrations\View\Renderer::page('تست یکپارچگی','<div class="bn-section">ok</div>');
foreach(['bn-brandbar','bn-card','ui-asset.php','brand-logo.png','bn-section','lang="fa"','dir="rtl"'] as $needle)
 $assert(str_contains($ui,$needle),'UI: '.$needle);
$assert(is_file($root.'/public/assets/babanoell-ui.css'),'shared Noeel CSS');
$asset=file_get_contents($root.'/public/assets/brand-logo.json');
$logo=json_decode((string)$asset,true);
$assert(is_array($logo)&&str_starts_with((string)base64_decode((string)($logo['base64']??''),true),"\x89PNG"),'same brand logo');
$assert(!is_file($root.'/public/assets/brand-logo.png'),'no image file type rejected by legacy updater');
$assert(!is_file($root.'/config/local.env.php'),'no production secrets bundled');
$assert(!is_file($root.'/storage/installed.lock'),'no installed state bundled');
$js=file_get_contents($root.'/public/update.php');
foreach(['IdentityAdminClient','super_admin','ReleaseTransaction::apply','ReleaseTransaction::recover','UpdateTool','int_csrf_ok'] as $code)
 $assert(str_contains((string)$js,$code),'update path: '.$code);
$assert(!str_contains((string)$js,"\$_POST['install_key']"),'updater no longer asks for installer key');
$assert(!\Babanoell\Integrations\Platform\ReleaseTransaction::pending($root.'/storage'),'no pending release state in bundle');
$assert(!\Babanoell\Integrations\Platform\ProxyContext::secure(['REMOTE_ADDR'=>'8.8.8.8','HTTP_X_FORWARDED_PROTO'=>'https'],[]),'untrusted forwarding rejected');
echo "Unified platform tests: $success passed\n";
