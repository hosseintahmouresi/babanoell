<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Documents tests are CLI-only.\n");
    exit(1);
}

$root = dirname(__DIR__);
$installer = is_file($root.'/public/install.php') ? $root.'/public/install.php' : $root.'/public/install.php.locked';
spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'Babanoell\\Documents\\';
    if (!str_starts_with($class, $prefix)) return;
    $file = $root . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require $file;
});

use Babanoell\Documents\App\Config;
use Babanoell\Documents\Domain\DocumentCatalog;
use Babanoell\Documents\Domain\DocumentCompletion;
use Babanoell\Documents\Domain\DocumentReview;
use Babanoell\Documents\Domain\UploadValidator;
use Babanoell\Documents\Http\HttpResponse;
use Babanoell\Documents\Http\HttpTransportInterface;
use Babanoell\Documents\Http\IdentityClient;
use Babanoell\Documents\Http\LoansClient;
use Babanoell\Documents\Http\Request;
use Babanoell\Documents\Security\Authenticator;
use Babanoell\Documents\Security\Principal;
use Babanoell\Documents\Service\EntityAccessService;
use Babanoell\Documents\Storage\PrivateStorage;
use Babanoell\Documents\Support\IniSize;

$tests=[];
$add=static function(string $name, callable $test) use (&$tests): void { $tests[$name]=$test; };
$assert=static function(bool $value,string $message='assertion failed'): void { if(!$value) throw new RuntimeException($message); };
$eq=static function(mixed $expected,mixed $actual,string $message='values differ'): void { if($expected!==$actual) throw new RuntimeException($message.' expected='.var_export($expected,true).' actual='.var_export($actual,true)); };
$throws=static function(callable $fn,string $class='Throwable'): void { try{$fn();}catch(Throwable $e){if($e instanceof $class)return;throw new RuntimeException('wrong exception '.$e::class);}throw new RuntimeException('exception not thrown'); };

$add('catalog covers Phase 3 document taxonomy',function()use($assert,$eq){
    $all=DocumentCatalog::all();
    foreach(['national_card','birth_certificate','employment_certificate','bank_statement','primary_check','secondary_check','guarantor_identity','credit_report','contract','invoice','supplemental','case_receipt','settlement_receipt'] as $code)$assert(isset($all[$code]),'missing '.$code);
    $eq('confidential',$all['contract']['visibility']);
    $eq('normal',$all['invoice']['visibility']);
});

$add('review validation requires a rejection reason',function()use($eq,$throws){
    $eq(['approved',''],DocumentReview::validate(' approved ',' '));
    $throws(static fn()=>DocumentReview::validate('rejected',''),InvalidArgumentException::class);
    $eq(['rejected','تصویر ناخوانا است'],DocumentReview::validate('rejected',' تصویر ناخوانا است '));
});

$add('completion only trusts the newest active upload per type',function()use($assert,$eq){
    $rows=[
        ['id'=>str_repeat('a',26),'document_type'=>'national_card','review_status'=>'rejected'],
        ['id'=>str_repeat('b',26),'document_type'=>'national_card','review_status'=>'approved'],
        ['id'=>str_repeat('c',26),'document_type'=>'birth_certificate','review_status'=>'approved'],
    ];
    $r=DocumentCompletion::evaluate(['national_card','birth_certificate','contract'],$rows);
    $assert($r['complete']===false);
    $eq(1,$r['approved_count']);
    $eq(['contract'],$r['missing']);
    $eq(['national_card'],$r['rejected']);
    $eq([], $r['pending']);
});

$add('completion treats an empty requirement set as complete',function()use($assert,$eq){$r=DocumentCompletion::evaluate([],[]);$assert($r['complete']===true);$eq(0,$r['required_count']);});

$add('ini size parser handles common PHP limits',function()use($eq){$eq(10485760,IniSize::toBytes('10M'));$eq(2147483648,IniSize::toBytes('2G'));$eq(PHP_INT_MAX,IniSize::toBytes('-1'));});

$add('upload validator accepts a genuine PNG and detects extension mismatch',function()use($throws,$eq){
    $tmp=tempnam(sys_get_temp_dir(),'doc_png_');
    $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',true);
    file_put_contents($tmp,$png);
    try{
        $v=(new UploadValidator(1024*1024))->validate(['name'=>'کارت-ملی.png','tmp_name'=>$tmp,'size'=>filesize($tmp),'error'=>UPLOAD_ERR_OK],false);
        $eq('image/png',$v->mimeType);$eq('png',$v->extension);$eq(64,strlen($v->sha256));
        $throws(static fn()=>(new UploadValidator(1024*1024))->validate(['name'=>'fake.jpg','tmp_name'=>$tmp,'size'=>filesize($tmp),'error'=>UPLOAD_ERR_OK],false),InvalidArgumentException::class);
    } finally {@unlink($tmp);}
});

$add('upload validator rejects empty and oversized files',function()use($throws){
    $empty=tempnam(sys_get_temp_dir(),'doc_empty_');
    $big=tempnam(sys_get_temp_dir(),'doc_big_');file_put_contents($big,str_repeat('A',2048));
    try{
        $throws(static fn()=>(new UploadValidator(1024))->validate(['name'=>'a.pdf','tmp_name'=>$empty,'size'=>0,'error'=>UPLOAD_ERR_OK],false),InvalidArgumentException::class);
        $throws(static fn()=>(new UploadValidator(1024))->validate(['name'=>'a.pdf','tmp_name'=>$big,'size'=>2048,'error'=>UPLOAD_ERR_OK],false),InvalidArgumentException::class);
    } finally {@unlink($empty);@unlink($big);}
});

$add('private storage stores by opaque key and blocks traversal',function()use($assert,$eq,$throws){
    $tmpFile=tempnam(sys_get_temp_dir(),'doc_store_');file_put_contents($tmpFile,"%PDF-1.4\n%%EOF\n");
    $base=sys_get_temp_dir().'/babanoell-doc-storage-'.bin2hex(random_bytes(4));
    $validated=(new UploadValidator(1024*1024))->validate(['name'=>'contract.pdf','tmp_name'=>$tmpFile,'size'=>filesize($tmpFile),'error'=>UPLOAD_ERR_OK],false);
    try{
        $storage=new PrivateStorage($base);$key=$storage->store($validated);$assert(!str_contains($key,'contract'));$path=$storage->path($key);$assert(is_file($path));$eq($validated->sha256,hash_file('sha256',$path));
        $throws(static fn()=>$storage->path('../secret'),RuntimeException::class);
    } finally {
        @unlink($tmpFile);
        if(is_dir($base)){ $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as$f){$f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname());}@rmdir($base); }
    }
});

$add('production config requires HTTPS service dependencies',function()use($assert){
    $bad=new Config('documents','production','localhost',3306,'db','user','pass','utf8mb4','http://identity.example','https://loans.example',8,10485760);
    $good=new Config('documents','production','localhost',3306,'db','user','pass','utf8mb4','https://identity.example','https://loans.example',8,10485760);
    $assert(!$bad->isReady());$assert($good->isReady());
});

$add('principal permissions are fail closed except super admin',function()use($assert){
    $u=new Principal(str_repeat('a',26),'کاربر',['operator'],['documents.view']);$assert($u->can('documents.view'));$assert(!$u->can('documents.manage'));
    $s=new Principal(str_repeat('b',26),'مدیر',['super_admin'],[]);$assert($s->can('documents.manage'));
});

$add('request accepts FastCGI redirected bearer authorization',function()use($eq){
    $r=new Request('GET','/api/v1/files', ['authorization'=>'Bearer abc.def']);$eq('abc.def',$r->bearer());
});

$add('authenticator maps Identity payload to a principal',function()use($assert,$eq){
    $transport=new class implements HttpTransportInterface{public function request(string $method,string $url,array $headers=[],?string $body=null,int $timeoutSeconds=6):HttpResponse{return new HttpResponse(200,['data'=>['user'=>['id'=>str_repeat('a',26),'display_name'=>'مدیر','status'=>'active'],'roles'=>['super_admin'],'permissions'=>['documents.view']]],'');}};
    $cfg=new Config('documents','staging','localhost',3306,'db','u','p','utf8mb4','https://identity.test','https://loans.test');
    $auth=(new Authenticator(new IdentityClient($cfg,$transport)))->authenticate(new Request('GET','/api/v1/stats',['authorization'=>'Bearer token']));
    $assert($auth['error']===null);$assert($auth['principal'] instanceof Principal);$eq('مدیر',$auth['principal']->displayName);$assert($auth['principal']->isSuperAdmin());
});

$add('entity access delegates loan ownership to Loans API',function()use($assert){
    $transport=new class implements HttpTransportInterface{public function request(string $method,string $url,array $headers=[],?string $body=null,int $timeoutSeconds=6):HttpResponse{return str_contains($url,'/api/v1/requests/')?new HttpResponse(200,['data'=>['request'=>['id'=>str_repeat('c',26)]]],''):new HttpResponse(200,['status'=>'ok'],'');}};
    $cfg=new Config('documents','staging','localhost',3306,'db','u','p','utf8mb4','https://identity.test','https://loans.test');
    $svc=new EntityAccessService(new LoansClient($cfg,$transport));$p=new Principal(str_repeat('a',26),'کاربر',[],['documents.view']);$r=$svc->assert('loan_request',str_repeat('c',26),'bearer',$p);$assert(($r['id']??'')===str_repeat('c',26));
});

$add('migration files expose seven unique forward versions',function()use($root,$assert,$eq){
    require_once $root.'/src/Database/Migration.php';$files=glob($root.'/database/migrations/*.php')?:[];sort($files,SORT_STRING);$seen=[];foreach($files as$f){$m=require$f;$assert($m instanceof Babanoell\Documents\Database\Migration);$assert(preg_match('/^\d{12}$/',$m->version())===1);$assert(!isset($seen[$m->version()]));$seen[$m->version()]=true;}$eq(7,count($seen));
});

$add('documents schema keeps owner required and all file FKs type-compatible',function()use($root,$assert){
    $m1=file_get_contents($root.'/database/migrations/202608270001_create_documents_table.php');$m2=file_get_contents($root.'/database/migrations/202608270002_create_document_versions_table.php');$assert(str_contains($m1,'owner_user_id CHAR(26) NOT NULL'));$assert(str_contains($m1,'id CHAR(26) NOT NULL PRIMARY KEY'));$assert(str_contains($m2,'document_id CHAR(26) NOT NULL'));$assert(str_contains($m2,'FOREIGN KEY(document_id) REFERENCES documents(id)'));
});

$add('migration runner is service isolated',function()use($root,$assert){$runner=file_get_contents($root.'/src/Database/MigrationRunner.php');$guard=file_get_contents($root.'/src/Database/DatabaseIsolationGuard.php');$assert(str_contains($runner,"documents_schema_migrations"));foreach(['users','roles','permissions','sessions','installment_plans','loan_requests']as$m)$assert(str_contains($guard,"'{$m}'"));});

$add('API exposes version review archive audit and short lived access routes',function()use($root,$assert){$src=file_get_contents($root.'/public/index.php');foreach(['/versions','/review','/archive','/audit','/access-token','access/(download|preview)']as$n)$assert(str_contains($src,$n));$assert(str_contains($src,"['1','true','yes','on']"));});

$add('access tokens are stored as hashes and consumed once',function()use($root,$assert){$m=file_get_contents($root.'/database/migrations/202608270006_create_document_access_tokens_table.php');$r=file_get_contents($root.'/src/Repository/PdoDocumentRepository.php');$assert(str_contains($m,'token_hash CHAR(64)'));$assert(!str_contains($m,'token_plain'));$assert(str_contains($r,"hash('sha256',\$plain)"));$assert(str_contains($r,'consumed_at IS NULL'));$assert(str_contains($r,'FOR UPDATE'));});

$add('owner override is restricted and relation codes are validated',function()use($root,$assert){$src=file_get_contents($root.'/src/Service/DocumentService.php');$assert(str_contains($src,'owner_override_denied'));$assert(str_contains($src,"documents.manage"));$assert(str_contains($src,"/^[a-z0-9][a-z0-9_.-]{1,63}$/"));});

$add('archive is atomic and auditable',function()use($root,$assert){$src=file_get_contents($root.'/src/Repository/PdoDocumentRepository.php');$pos=strpos($src,'public function archive');$chunk=substr($src,$pos,2200);foreach(['beginTransaction','FOR UPDATE','rowCount()!==1','document.archived','commit()','rollBack()']as$n)$assert(str_contains($chunk,$n),$n);});

$add('installer checks server upload limits and dependency health',function()use($root,$installer,$assert){$src=file_get_contents($installer);foreach(['pdo_mysql','fileinfo','upload_max_filesize','post_max_size','documents_probe','DatabaseIsolationGuard::assertDocumentsDatabase','identity_url','loans_url']as$n)$assert(str_contains($src,$n),$n);});

$add('updater is documents scoped and protects private state',function()use($root,$assert){$src=file_get_contents($root.'/src/Update/UpdateTool.php');$assert(str_contains($src,"str_starts_with(\$entry,'documents/')"));$assert(str_contains($src,"'config/local.env.php'"));$assert(str_contains($src,"str_starts_with(\$relative,'storage/')"));$assert(str_contains($src,"'public/install.php'"));});

$add('UI uses the canonical palette external assets and exact logo',function()use($root,$installer,$assert,$eq){$css=file_get_contents($root.'/public/assets/babanoell-ui.css');foreach(['#1D3559','#D0E2F2','#EDA900']as$c)$assert(str_contains($css,$c));foreach([$installer,$root.'/public/update.php']as$f){$src=file_get_contents($f);$assert(!preg_match('/<style\b/i',$src));$assert(!preg_match('/style\s*=\s*["\']/i',$src));}$identityLogo=dirname($root).'/identity/public/assets/brand-logo.png';if(is_file($identityLogo))$eq(hash_file('sha256',$identityLogo),hash_file('sha256',$root.'/public/assets/brand-logo.png'));});

$add('private service root is blocked from direct web access',function()use($root,$assert){$h=file_get_contents($root.'/.htaccess');foreach(['config','database','storage','src','tests']as$n)$assert(str_contains($h,$n));$assert(str_contains($h,'public/index.php'));});

$add('file responses disable sniffing and caching',function()use($root,$assert){$src=file_get_contents($root.'/src/Http/FileResponse.php');foreach(['X-Content-Type-Options: nosniff','Cache-Control: private, no-store','Content-Disposition']as$n)$assert(str_contains($src,$n));});


$add('catalog exposes the canonical Loans document-code contract',function()use($eq){
    $expected=['national_card','national_card_front','national_card_back','birth_certificate','portrait','sana_certificate','employment_certificate','business_license','activity_license','payslip','bank_statement','account_statement','tax_return','residence_document','workplace_document','signature_certificate','primary_check','secondary_check','blank_check_image','guarantor_identity','guarantor_income','guarantor_check','credit_report','bank_validation','contract','signed_contract','invoice','supplemental','completed_check','completed_check_files','signed_contract_files','signed_invoice_files','other_completed_files','additional_documents','case_receipt','transfer_receipt','settlement_receipt','bank_settlement_receipt','cooperation_identity','cooperation_license','cooperation_contract'];
    $eq($expected,DocumentCatalog::codes(),'Documents document catalog diverged from the canonical Phase 0 contract');
});

$add('catalog confidentiality cannot be downgraded by API input',function()use($root,$assert){
    $src=file_get_contents($root.'/src/Service/DocumentService.php');
    $assert(str_contains($src,"\$visibility=\$catalog['visibility']==='confidential'?'confidential':\$requestedVisibility"),'confidentiality floor missing');
});

$add('create replace and review retrieve before commit so storage rollback remains consistent',function()use($root,$assert){
    $src=file_get_contents($root.'/src/Repository/PdoDocumentRepository.php');
    foreach(['create','replace','review'] as $method){
        $start=strpos($src,'public function '.$method);$assert($start!==false,'missing '.$method);
        $next=strpos($src,'public function ',$start+20);$chunk=substr($src,$start,$next===false?null:$next-$start);
        $getPos=strpos($chunk,'$this->get(');$commitPos=strpos($chunk,'$this->pdo->commit()');
        $assert($getPos!==false&&$commitPos!==false&&$getPos<$commitPos,$method.' must retrieve before commit');
        $assert(str_contains($chunk,'rollBack()'),$method.' rollback missing');
    }
});

$add('completion ordering follows newest uploaded file rather than mutable document timestamp',function()use($root,$assert){
    $src=file_get_contents($root.'/src/Repository/PdoDocumentRepository.php');
    $start=strpos($src,'public function listByEntity');$chunk=substr($src,$start,1200);
    $assert(str_contains($chunk,'ORDER BY v.uploaded_at DESC,v.version DESC,d.id DESC'),'entity document order is not upload authoritative');
    $assert(!str_contains($chunk,'ORDER BY d.updated_at DESC'),'mutable review timestamp must not drive completion');
});

$add('global document list is active-only by default and supports explicit archived filtering',function()use($root,$assert){
    $repo=file_get_contents($root.'/src/Repository/PdoDocumentRepository.php');
    $api=file_get_contents($root.'/public/index.php');
    $assert(str_contains($repo,"string\$documentStatus='active'"));
    $assert(str_contains($repo,"['active','archived']"));
    $assert(str_contains($api,"['active','archived','all']"));
});

$add('one-time token consumption verifies the atomic consume update',function()use($root,$assert){
    $src=file_get_contents($root.'/src/Repository/PdoDocumentRepository.php');
    $start=strpos($src,'public function consumeAccessToken');$chunk=substr($src,$start,1800);
    foreach(['FOR UPDATE','consumed_at IS NULL','rowCount()!==1','rollBack()','commit()'] as $needle)$assert(str_contains($chunk,$needle),$needle);
});

$add('installer requires ZipArchive and secures generated private config and lock',function()use($root,$installer,$assert){
    $src=file_get_contents($installer);
    foreach(["class_exists('ZipArchive')",'@chmod($local,0640)','@chmod($lock,0640)'] as $needle)$assert(str_contains($src,$needle),$needle);
});

$add('runtime private state is protected from updater and direct web delivery',function()use($root,$assert){
    $updater=file_get_contents($root.'/src/Update/UpdateTool.php');
    $htaccess=file_get_contents($root.'/.htaccess');
    foreach(["'config/local.env.php'","str_starts_with(\$relative,'storage/')","'public/install.php'","'public/install.php.locked'"] as $needle)$assert(str_contains($updater,$needle),'missing updater protection '.$needle);
    foreach(['storage','config','database'] as $needle)$assert(str_contains($htaccess,$needle),'missing web deny '.$needle);
});


$add('database isolation rejects foreign service markers even after partial Documents schema exists',function()use($root,$assert){
    $src=file_get_contents($root.'/src/Database/DatabaseIsolationGuard.php');
    $assert(str_contains($src,"if(\$info['foreign_markers']!==[]){"),'foreign markers must always fail closed');
    $assert(!str_contains($src,"foreign_markers']!==[]&&\$info['own_markers']===[]"),'partial own schema must not bypass isolation');
});

$add('runtime errors are logged without exposing internal exception messages to API clients',function()use($root,$assert){
    $src=file_get_contents($root.'/public/index.php');
    $assert(str_contains($src,"error_log('[documents] runtime error: '.\$e->getMessage())"),'runtime error must be logged');
    $assert(str_contains($src,"JsonResponse::error(409,'operation_failed','عملیات کامل نشد."),'public runtime message must be generic');
    $assert(!str_contains($src,"JsonResponse::error(409,'operation_failed',\$e->getMessage())"),'raw runtime exception leaked');
});

$add('private storage has defense in depth web denial files',function()use($root,$assert){
    foreach([$root.'/storage/.htaccess',$root.'/storage/private/.htaccess'] as $file){
        $assert(is_file($file),'missing '.basename(dirname($file)).' .htaccess');
        $src=file_get_contents($file);$assert(str_contains($src,'Require all denied'),'storage web deny missing');
    }
});

$add('phase 2A Documents request context and service signature contract are stable',function()use($assert,$eq){
    \Babanoell\Documents\Platform\RequestContext::set('req_phase2a_123456');
    $h=\Babanoell\Documents\Platform\ServiceAuth::outbound('documents','GET','/api/v1/entities/loan_request/abc/documents',null,'shared-secret-123');
    $eq('req_phase2a_123456',$h['X-Request-Id']);$eq('documents',$h['X-Noel-Service']);
    $v=\Babanoell\Documents\Platform\ServiceAuth::verify($h['X-Noel-Service'],$h['X-Noel-Timestamp'],$h['X-Noel-Signature'],'GET','/api/v1/entities/loan_request/abc/documents',null,'shared-secret-123','required');$assert($v['ok']===true&&$v['authenticated']===true);
});
$add('phase 2A Documents boundary remains file domain only',function()use($assert,$eq){
    $c=\Babanoell\Documents\Platform\ServiceBoundary::describe();$eq('documents',$c['service']);foreach(['documents','document_versions','links','reviews','storage']as$n)$assert(in_array($n,$c['owns'],true));foreach(['loan_requests','settlements','merchants']as$n)$assert(!in_array($n,$c['owns'],true));
});
$add('phase 2A Documents API and service clients share platform contract',function()use($root,$assert){
    $index=file_get_contents($root.'/public/index.php');$assert(str_contains($index,'/api/v1/meta/platform-contract'));$assert(str_contains($index,'RequestContext::initialize'));
    foreach(['IdentityClient.php','LoansClient.php']as$f){$src=file_get_contents($root.'/src/Http/'.$f);$assert(str_contains($src,'ServiceAuth::outbound'));}
    $json=file_get_contents($root.'/src/Http/JsonResponse.php');$assert(str_contains($json,'X-Noel-Api-Version'));$assert(str_contains($json,'responseMeta'));
});

$add('phase 2A Documents pagination and audit metadata contracts are standardized',function()use($root,$assert,$eq){
    $eq(['page'=>2,'per_page'=>25,'total'=>51,'total_pages'=>3],\Babanoell\Documents\Http\Pagination::fromResult(['page'=>2,'per_page'=>25,'total'=>51]));
    $repo=file_get_contents($root.'/src/Repository/PdoDocumentRepository.php');$assert(str_contains($repo,'RequestContext::auditMeta()'));
    $index=file_get_contents($root.'/public/index.php');$assert(str_contains($index,"'pagination'=>\\Babanoell\\Documents\\Http\\Pagination::fromResult"));
});

$passed=0;$failed=0;
foreach($tests as$name=>$test){try{$test();echo"[PASS] {$name}\n";$passed++;}catch(Throwable $e){echo"[FAIL] {$name}: {$e->getMessage()}\n";$failed++;}}
echo 'Documents tests: '.($passed+$failed).' run, '.$failed." failed.\n";
exit($failed?1:0);
