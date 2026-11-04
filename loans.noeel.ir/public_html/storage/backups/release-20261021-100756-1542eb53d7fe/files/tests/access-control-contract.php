<?php
declare(strict_types=1);require dirname(__DIR__).'/bootstrap.php';
use Babanoell\Loans\Security\Principal; use Babanoell\Loans\Security\RequestAccessPolicy;
$p=new Principal('aaaaaaaaaaaaaaaaaaaaaaaaaa','x',['bank_expert'],['loans.requests.read','loans.requests.update','loans.requests.transition'],[]);$policy=new RequestAccessPolicy($p);if(!$policy->canRead(['assigned_bank_user_id'=>'aaaaaaaaaaaaaaaaaaaaaaaaaa']))throw new RuntimeException('assigned access failed');if($policy->canRead(['assigned_bank_user_id'=>'bbbbbbbbbbbbbbbbbbbbbbbbbb']))throw new RuntimeException('cross record access leak');echo "access-control-contract: ok\n";
