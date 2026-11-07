<?php
declare(strict_types=1);

$fixture=json_decode((string)file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
$root=$fixture['root'];
spl_autoload_register(static function(string $class)use($root):void{
    foreach(['Auth'=>'identity','Loans'=>'loans','Documents'=>'documents','Partners'=>'partners','Finance'=>'finance','Integrations'=>'integrations']as$ns=>$service){
        $prefix='Babanoell\\'.$ns.'\\';
        if(str_starts_with($class,$prefix)){
            $file=$root.'/'.$service.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
            if(is_file($file))require$file;
        }
    }
});
$connect=static function(string $service='')use($fixture):PDO{
    // No supplied database host/DSN: this fixture can reach only its disposable socket.
    $dsn='mysql:unix_socket='.$fixture['socket'].';charset=utf8mb4';
    if($service!=='')$dsn.=';dbname=test_'.$service;
    return new PDO($dsn,$fixture['db_user'],'',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
};

if(($argv[2]??'')==='prepare'){
    $db=$connect();$migrations=[];
    foreach(['identity'=>'Auth','loans'=>'Loans','documents'=>'Documents','partners'=>'Partners','finance'=>'Finance','integrations'=>'Integrations']as$service=>$ns){
        $db->exec('CREATE DATABASE test_'.$service.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo=$connect($service);
        if($service==='integrations'){
            $first=Babanoell\Integrations\Database\Migrator::up($pdo,$root.'/'.$service);
            $second=Babanoell\Integrations\Database\Migrator::up($pdo,$root.'/'.$service);
        }else{
            $class='Babanoell\\'.$ns.'\\Database\\MigrationRunner';
            $runner=new $class($pdo,$root.'/'.$service.'/database/migrations');
            $first=$runner->up();$second=$runner->up();
        }
        if($second!==[])throw new RuntimeException($service.' migrations are not repeatable');
        $migrations[$service]=count($first);
    }
    $pdo=$connect('identity');
    $roleId=substr(hash('sha256','integration:role:super_admin'),0,26);
    $s=$pdo->prepare("INSERT INTO roles(id,name,label,is_system) VALUES(:id,'super_admin','Synthetic owner',1) ON DUPLICATE KEY UPDATE label=VALUES(label)");
    $s->execute(['id'=>$roleId]);
    $roleId=(string)$pdo->query("SELECT id FROM roles WHERE name='super_admin'")->fetchColumn();
    $s=$pdo->prepare('INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT :id,id FROM permissions');$s->execute(['id'=>$roleId]);
    $password=password_hash($fixture['password'],PASSWORD_BCRYPT,['cost'=>4]);$accounts=[];
    foreach(['super_admin','admin','acceptor','applicant','bank_expert','operations_expert','finance_expert','contract_expert']as$i=>$role){
        $id=substr(hash('sha256','integration:user:'.$role),0,26);$email=$role.'@example.test';
        $s=$pdo->prepare("INSERT INTO users(id,email,mobile,display_name,status,password_hash) VALUES(:id,:email,:mobile,:name,'active',:hash)");
        $s->execute(['id'=>$id,'email'=>$email,'mobile'=>'091900000'.str_pad((string)$i,2,'0',STR_PAD_LEFT),'name'=>'Synthetic '.$role,'hash'=>$password]);
        $s=$pdo->prepare('INSERT INTO user_roles(user_id,role_id) SELECT :id,id FROM roles WHERE name=:name');
        $s->execute(['id'=>$id,'name'=>$role]);if($s->rowCount()!==1)throw new RuntimeException('Fixture role missing: '.$role);
        $accounts[$role]=['id'=>$id,'email'=>$email];
    }
    // Initialize storage with the real runtime protection policy before distribution checks.
    new Babanoell\Documents\Storage\PrivateStorage($root.'/documents/storage');
    new Babanoell\Documents\Storage\PrivateStorage($root.'/documents/storage/private');
    $firstDue=Babanoell\Loans\Support\PersianDate::isoToJalali(gmdate('Y-m-d',time()+90*86400));
    echo json_encode(['accounts'=>$accounts,'migrations'=>$migrations,'first_due'=>$firstDue],JSON_THROW_ON_ERROR)."\n";
    exit(0);
}

if(($argv[2]??'')!=='regressions')throw new RuntimeException('Unknown fixture command');
$checks=[];
$check=static function(string$name,callable$run)use(&$checks):void{
    try{$run();$checks[]=['name'=>$name,'passed'=>true];}
    catch(Throwable$e){$checks[]=['name'=>$name,'passed'=>false,'type'=>get_class($e),'code'=>$e->getCode()];}
};
$assert=static function(bool$ok):void{if(!$ok)throw new RuntimeException('Regression assertion failed');};
$pdo=$connect('loans');$repo=new Babanoell\Loans\Repository\PdoLoanRepository($pdo);
$actor=substr(hash('sha256','integration:user:applicant'),0,26);
$acceptor=substr(hash('sha256','integration:user:acceptor'),0,26);
$plans=new Babanoell\Loans\Service\PlanService($repo,new Babanoell\Loans\Domain\PlanSettingsValidator());
$plan=$plans->create($actor,['code'=>'NATIVE-PDO','name'=>'Native PDO regression plan','type'=>'market','settings'=>['min_amount'=>1,'max_amount'=>100000000,'active_installments'=>[6],'fixed_monthly_rate'=>2,'prepayment_percent'=>20]]);
$plan=$plans->status($plan['id'],'active');
$calc=new Babanoell\Loans\Service\CalculationService($repo,new Babanoell\Loans\Calculation\CalculatorRegistry());
$requests=new Babanoell\Loans\Service\RequestService($repo,$calc,new Babanoell\Loans\Domain\ScheduleGenerator());
$loan=$requests->create($actor,['plan_id'=>$plan['id'],'requested_amount'=>1000000,'installment_count'=>6,'acceptor_assignment_mode'=>'open_acceptor_marketplace','request_public_summary'=>'unique-regression-summary'], 'regression-request',Babanoell\Loans\Domain\RequestOrigin::APPLICANT);
$merchant=substr(hash('sha256','integration:merchant:regression'),0,26);
$repo->upsertShadowMerchant(['id'=>$merchant,'code'=>'REGRESSION','name'=>'Synthetic merchant','owner_user_id'=>$acceptor,'status'=>'active']);
$check('marketplace search matches summary with native prepares',static function()use($repo,$loan,$assert):void{
    $result=$repo->listMarketplace(1,25,'unique-regression-summary');
    $assert($result['total']===1&&$result['items'][0]['id']===$loan['id']);
    $assert(!array_key_exists('national_code',$result['items'][0]));
});
$check('marketplace claim sets both ownership fields atomically',static function()use($repo,$loan,$acceptor,$merchant,$assert):void{
    $claimed=$repo->claimMarketplace($loan['id'],$acceptor,$merchant,$acceptor,['merchant_id'=>$merchant,'can_claim'=>true,'enabled'=>true,'allow_all_country'=>true]);
    $assert($claimed['acceptor_user_id']===$acceptor&&$claimed['marketplace_claimed_by_user_id']===$acceptor&&$claimed['marketplace_status']==='claimed');
    try{$repo->claimMarketplace($loan['id'],str_repeat('b',26),$merchant,str_repeat('b',26),['merchant_id'=>$merchant,'can_claim'=>true,'enabled'=>true,'allow_all_country'=>true]);}
    catch(DomainException){return;}
    throw new RuntimeException('Duplicate claim was accepted');
});
$partners=$connect('partners');$partnerRepo=new Babanoell\Partners\Repository\PdoPartnerRepository($partners);
$check('cooperation shadow import is repeatable with timestamp fallback',static function()use($partnerRepo,$actor,$assert,$partners):void{
    $id=substr(hash('sha256','integration:cooperation:regression'),0,26);
    $row=['id'=>$id,'request_no'=>'REGRESSION-COOP','request_type'=>'individual','status'=>'submitted','created_at'=>'2026-01-02 03:04:05','data'=>['full_name'=>'Synthetic']];
    $assert($partnerRepo->importLoansShadowBatch('cooperation_requests',[$row],$actor)===1);
    $assert($partnerRepo->importLoansShadowBatch('cooperation_requests',[$row],$actor)===1);
    $s=$partners->prepare('SELECT submitted_at,created_at FROM cooperation_requests WHERE id=:id');$s->execute(['id'=>$id]);$records=$s->fetchAll();
    $assert(count($records)===1&&str_starts_with($records[0]['submitted_at'],$row['created_at'])&&str_starts_with($records[0]['created_at'],$row['created_at']));
    $row['submitted_at']='2026-02-03 04:05:06';
    $row['id']=substr(hash('sha256','integration:cooperation:explicit-date'),0,26);
    $row['request_no']='REGRESSION-COOP-EXPLICIT';
    $partnerRepo->importLoansShadowBatch('cooperation_requests',[$row],$actor);
    $s->execute(['id'=>$row['id']]);$saved=$s->fetch();
    $assert(str_starts_with($saved['submitted_at'],$row['submitted_at'])&&str_starts_with($saved['created_at'],$row['created_at']));
});
$check('bank case search and assignment scope use native prepares',static function()use($repo,$pdo,$loan,$actor,$assert):void{
    $s=$pdo->prepare("UPDATE loan_requests SET status='under_review',assigned_bank_user_id=:actor WHERE id=:id");
    $s->execute(['actor'=>$actor,'id'=>$loan['id']]);
    $result=$repo->bankCases(1,25,$actor,false,['q'=>$loan['request_no']]);
    $assert($result['total']===1&&$result['items'][0]['id']===$loan['id']);
    $denied=$repo->bankCases(1,25,str_repeat('c',26),false,['q'=>$loan['request_no']]);
    $assert($denied['total']===0&&$denied['items']===[]);
});
$check('task search accepts native prepares',static function()use($pdo,$actor,$assert):void{
    $tasks=new Babanoell\Loans\Workflow\TaskRepository($pdo);
    $result=$tasks->list(['search'=>'absent-synthetic-task'],[],$actor,true);
    $assert($result['total']===0&&$result['items']===[]);
});
$check('merchant search accepts native prepares',static function()use($partnerRepo,$assert):void{
    $result=$partnerRepo->listMerchants(1,25,'absent-synthetic-merchant');
    $assert($result['total']===0&&$result['items']===[]);
});
$check('cooperation search preserves filter results with native prepares',static function()use($partnerRepo,$assert):void{
    $result=$partnerRepo->listCooperation(1,25,'submitted','individual','REGRESSION-COOP');
    $assert($result['total']===2&&count($result['items'])===2);
});
$originalTimezone=date_default_timezone_get();
foreach(['UTC','Asia/Tehran']as$timezone){
    date_default_timezone_set($timezone);
    $check('verification expiry is UTC in '.$timezone,static function()use($repo,$pdo,$loan,$actor,$assert,$timezone):void{
        $bank=new Babanoell\Loans\Service\BankCreditService($repo);
        $create=static fn(string$key):array=>$repo->createVerificationRequest($loan['id'],'identity_match','shahkar',$key,str_repeat('a',64),str_repeat('b',64),$actor,120);
        $live=$create('live-'.$timezone);
        $result=$bank->complete($live['id'],$actor,['status'=>'verified','provider_reference'=>'synthetic']);
        $assert($result['status']==='verified');
        $expired=$create('expired-'.$timezone);
        $s=$pdo->prepare('UPDATE verification_requests SET expires_at=:time WHERE id=:id');
        $s->execute(['time'=>gmdate('Y-m-d H:i:s',time()-60),'id'=>$expired['id']]);
        try{$bank->complete($expired['id'],$actor,['status'=>'verified']);}
        catch(InvalidArgumentException){$assert($repo->getVerificationRequest($expired['id'])['status']==='queued');return;}
        throw new RuntimeException('Expired verification was accepted');
    });
    $check('contract OTP expiry cooldown and attempts in '.$timezone,static function()use($partners,$actor,$assert):void{
        $contracts=new Babanoell\Partners\Contract\ContractRepository($partners);
        $create=static fn():array=>$contracts->createContract($actor,[
            'contract_kind'=>'loan_application','subject_type'=>'loan_request','subject_id'=>bin2hex(random_bytes(13)),
            'signer_user_id'=>$actor,'signer_mobile'=>'09121234567','mode'=>'digital','status'=>'pending',
            'template_id'=>null,'template_code'=>'synthetic-utc','template_version'=>1,'template_hash'=>str_repeat('a',64),
            'rendered_content'=>'<p>Synthetic UTC regression</p>',
        ]);
        $contract=$create();$id=$contract['id'];$otp='654321';
        $contracts->issueOtp($id,password_hash($otp,PASSWORD_BCRYPT,['cost'=>4]),120,60,null,null);
        $blocked=false;try{$contracts->issueOtp($id,'unused',120,60,null,null);}catch(RuntimeException){$blocked=true;}
        $assert($blocked);
        $blocked=false;try{$contracts->verifyOtpAndSign($id,'000000',null,null);}catch(RuntimeException){$blocked=true;}
        $assert($blocked&&$contracts->latestOtp($id)['attempts']===1);
        $signed=$contracts->verifyOtpAndSign($id,$otp,null,null);
        $assert($signed['status']==='signed'&&$signed['rendered_hash']===$contract['rendered_hash']);
        $expired=$create();$challenge=$contracts->issueOtp($expired['id'],password_hash($otp,PASSWORD_BCRYPT,['cost'=>4]),120,60,null,null);
        $s=$partners->prepare('UPDATE contract_otp_challenges SET expires_at=:time WHERE id=:id');
        $s->execute(['time'=>gmdate('Y-m-d H:i:s',time()-60),'id'=>$challenge['id']]);
        try{$contracts->verifyOtpAndSign($expired['id'],$otp,null,null);}
        catch(RuntimeException){$assert($contracts->contract($expired['id'])['status']==='sent');return;}
        throw new RuntimeException('Expired contract OTP was accepted');
    });
}
date_default_timezone_set($originalTimezone);
echo json_encode($checks,JSON_THROW_ON_ERROR)."\n";
exit(count(array_filter($checks,static fn($x)=>!$x['passed']))?1:0);
