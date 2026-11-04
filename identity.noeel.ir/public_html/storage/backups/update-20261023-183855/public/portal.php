<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require $root.'/src/Sso/UserPortal.php';
NoelUserPortal::run('identity',$root);
