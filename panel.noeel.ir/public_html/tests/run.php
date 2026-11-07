<?php
declare(strict_types=1);
ob_start();

$root=dirname(__DIR__);
$installer=is_file($root.'/public/install.php')?$root.'/public/install.php':$root.'/public/install.php.locked';
spl_autoload_register(static function(string $class)use($root):void{$prefix='Babanoell\\Panel\\';if(!str_starts_with($class,$prefix))return;$f=$root.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($f))require$f;});

use Babanoell\Panel\App\Config;
use Babanoell\Panel\Auth\PanelSession;
use Babanoell\Panel\Http\HttpResponse;
use Babanoell\Panel\Http\IdentityGatewayInterface;
use Babanoell\Panel\Security\TokenVault;
use Babanoell\Panel\Support\PersianDateTime;
use Babanoell\Panel\Update\UpdateTool;

$sessionDir=sys_get_temp_dir().'/panel-tests-sessions';if(!is_dir($sessionDir))mkdir($sessionDir,0770,true);session_save_path($sessionDir);session_name('panel_test');session_start();

class SkippedReleaseArtifact extends RuntimeException {}
$tests=[];$add=static function(string $name,callable $fn)use(&$tests){$tests[$name]=$fn;};
$assert=static function(bool $value,string $message='assertion failed'){if(!$value)throw new RuntimeException($message);};
$eq=static function(mixed $a,mixed $b,string $message='not equal'){if($a!==$b)throw new RuntimeException($message.' '.var_export($a,true).' !== '.var_export($b,true));};

final class FakeIdentityGateway implements IdentityGatewayInterface {
    public HttpResponse $loginResponse; public array $meResponses=[]; public HttpResponse $refreshResponse; public HttpResponse $logoutResponse; public int $logoutCalls=0; public int $refreshCalls=0;
    public function __construct(){
        $this->loginResponse=new HttpResponse(200,['data'=>['session'=>['session_credential'=>'access-one','refresh_credential'=>'refresh-one','access_expires_at'=>'2026-08-26T12:00:00Z','refresh_expires_at'=>'2026-09-09T12:00:00Z']]]);
        $this->refreshResponse=new HttpResponse(200,['data'=>['session'=>['session_credential'=>'access-two','refresh_credential'=>'refresh-two']]]);
        $this->logoutResponse=new HttpResponse(200,['data'=>['status'=>'logged_out']]);
    }
    public function health():HttpResponse{return new HttpResponse(200,['status'=>'ok']);}
    public function login(string $identifier,string $password,?string $clientIp,?string $userAgent):HttpResponse{return $this->loginResponse;}
    public function me(string $accessCredential,?string $clientIp,?string $userAgent):HttpResponse{return array_shift($this->meResponses)??new HttpResponse(200,['data'=>['user'=>['id'=>'user-1','display_name'=>'وب‌مستر بابانوئل','status'=>'active'],'roles'=>['super_admin'],'permissions'=>['system.read'],'session'=>['access_expires_at'=>'2026-08-26T12:00:00Z']]]);}
    public function refresh(string $refreshCredential,?string $clientIp,?string $userAgent):HttpResponse{$this->refreshCalls++;return $this->refreshResponse;}
    public function logout(string $accessCredential,bool $allSessions,?string $clientIp,?string $userAgent):HttpResponse{$this->logoutCalls++;return $this->logoutResponse;}
}

function panel_config(): Config { return new Config('panel','staging','https://identity.example.test',TokenVault::generateKey(),6,1800,28800); }
function fresh_session(): void { $_SESSION=[]; session_regenerate_id(true); }

$add('jalali formatter uses Tehran and Persian digits', function()use($assert){$d=new DateTimeImmutable('2026-08-26T10:00:00Z');$s=PersianDateTime::full($d);$assert(str_contains($s,'۱۴۰۵'));$assert(str_contains($s,'شهریور'));$assert(str_contains($s,'ساعت'));});
$add('production config requires https and staging config can be ready', function()use($assert){$key=TokenVault::generateKey();$assert(!(new Config('panel','production','http://identity.test',$key))->isReady());$assert((new Config('panel','production','https://identity.test',$key))->isReady());$assert((new Config('panel','staging','http://127.0.0.1:8081',$key))->isReady());});
$add('token vault roundtrip hides plaintext', function()use($assert,$eq){$v=new TokenVault(TokenVault::generateKey());$s=$v->seal('very-secret-credential');$assert(!str_contains($s,'very-secret'));$eq('very-secret-credential',$v->open($s));});
$add('panel session login caches role identity and encrypts credentials', function()use($assert){fresh_session();$g=new FakeIdentityGateway();$p=new PanelSession(panel_config(),$g,new TokenVault(panel_config()->sessionEncryptionKey()));/* different key would fail, recreate */});
// Replace previous smoke with correctly shared config/key.
$tests['panel session login caches role identity and encrypts credentials']=function()use($assert){fresh_session();$c=panel_config();$g=new FakeIdentityGateway();$p=new PanelSession($c,$g,new TokenVault($c->sessionEncryptionKey()));$r=$p->login('admin@example.test','password','127.0.0.1','Test');$assert($r['ok']);$assert($p->isSuperAdmin());$assert(isset($_SESSION['panel_access'])&&!str_contains((string)$_SESSION['panel_access'],'access-one'));$assert(($p->cachedIdentity()['user']['id']??null)==='user-1');};
$add('invalid credentials are mapped to Persian public message',function()use($assert){fresh_session();$c=panel_config();$g=new FakeIdentityGateway();$g->loginResponse=new HttpResponse(401,['error'=>['code'=>'invalid_credentials','message'=>'Invalid credentials.']]);$p=new PanelSession($c,$g,new TokenVault($c->sessionEncryptionKey()));$r=$p->login('x','bad','127.0.0.1','Test');$assert(!$r['ok']);$assert($r['code']==='invalid_credentials');$assert(str_contains($r['message'],'رمز'));});
$add('expired access refreshes automatically',function()use($assert){fresh_session();$c=panel_config();$g=new FakeIdentityGateway();$p=new PanelSession($c,$g,new TokenVault($c->sessionEncryptionKey()));$assert($p->login('a@b.c','password','127.0.0.1','Test')['ok']);$g->meResponses=[new HttpResponse(401,['error'=>['code'=>'invalid_credentials','message'=>'Invalid']]),new HttpResponse(200,['data'=>['user'=>['id'=>'user-1','display_name'=>'وب‌مستر','status'=>'active'],'roles'=>['super_admin'],'permissions'=>[],'session'=>[]]])];$id=$p->identity('127.0.0.1','Test');$assert($id!==null);$assert($g->refreshCalls===1);});
$add('logout calls identity and clears local session',function()use($assert){fresh_session();$c=panel_config();$g=new FakeIdentityGateway();$p=new PanelSession($c,$g,new TokenVault($c->sessionEncryptionKey()));$assert($p->login('a@b.c','password','127.0.0.1','Test')['ok']);$p->logout('127.0.0.1','Test');$assert($g->logoutCalls===1);$assert($p->cachedIdentity()===null);});
$add('update tool verifies manifest and rejects private state or corrupt files',function()use($root,$assert){
    if(!class_exists('ZipArchive'))throw new SkippedReleaseArtifact('ZIP extension unavailable');
    $version=(require $root.'/config/version.php')['version'];
    $files=['public/index.php'=>'<?php','public/update.php'=>'<?php','public/assets/panel.css'=>'x','public/ui-asset.php'=>'<?php','src/App/Config.php'=>'<?php','src/Update/PackageIntegrity.php'=>'<?php','config/version.php'=>"<?php return ['version'=>'".$version."'];"];
    $manifest=['service'=>'panel','version'=>$version,'files'=>[]];
    foreach($files as$path=>$content)$manifest['files'][$path]=['sha256'=>hash('sha256',$content),'bytes'=>strlen($content)];
    $tmp=sys_get_temp_dir().'/panel-update-'.bin2hex(random_bytes(4)).'.zip';
    try{
        $z=new ZipArchive();$z->open($tmp,ZipArchive::CREATE);
        foreach($files as$path=>$content)$z->addFromString('panel/'.$path,$content);
        $z->addFromString('panel/release-manifest.json',json_encode($manifest,JSON_THROW_ON_ERROR));$z->close();
        $plan=(new UpdateTool())->plan($tmp);$assert(count($plan['files'])===count($files)+1);$assert($plan['protected']===0);
        $z=new ZipArchive();$z->open($tmp);$z->addFromString('panel/public/index.php','corrupted');$z->close();
        $rejected=false;try{(new UpdateTool())->plan($tmp);}catch(RuntimeException){$rejected=true;}$assert($rejected,'corrupt package accepted');
        $z=new ZipArchive();$z->open($tmp);$z->addFromString('panel/public/index.php',$files['public/index.php']);$z->addFromString('panel/config/local.env.php','synthetic private value');$z->close();
        $rejected=false;try{(new UpdateTool())->plan($tmp);}catch(RuntimeException){$rejected=true;}$assert($rejected,'private configuration accepted');
    }finally{if(is_file($tmp))unlink($tmp);}
});
$add('update tool rejects traversal',function()use($assert){if(!class_exists('ZipArchive'))return;$tmp=sys_get_temp_dir().'/panel-bad-'.bin2hex(random_bytes(4)).'.zip';$z=new ZipArchive();$z->open($tmp,ZipArchive::CREATE);$z->addFromString('panel/../evil.php','x');$z->close();$failed=false;try{(new UpdateTool())->plan($tmp);}catch(Throwable){$failed=true;}unlink($tmp);$assert($failed);});
$add('ui uses approved palette and external stylesheet only',function()use($root,$installer,$assert){if(!is_file($installer))throw new SkippedReleaseArtifact('Installer omitted from update package');$css=file_get_contents($root.'/public/assets/panel.css');$assert(str_contains($css,'#1D3559'));$assert(str_contains($css,'#D0E2F2'));$assert(str_contains($css,'#EDA900'));foreach([$root.'/public/index.php',$installer,$root.'/public/update.php'] as $f){$source=file_get_contents($f);$assert(!preg_match('/<style\b/i',$source));$assert(!preg_match('/\sstyle\s*=\s*["\']/i',$source));}});
$add('visible brand logo matches the canonical identity asset',function()use($root,$assert){$assert(is_file($root.'/public/assets/brand-logo.png'));$css=file_get_contents($root.'/public/assets/panel.css');$assert(str_contains($css,'.bn-logo{'));$assert(str_contains($css,'.bn-panel-sidebar .bn-logo'));});
$add('installer never asks for identity password or token',function()use($root,$installer,$assert){if(!is_file($installer))throw new SkippedReleaseArtifact('Installer omitted from update package');$s=file_get_contents($installer);$assert(!str_contains($s,'AUTH_DB_PASSWORD'));$assert(!preg_match('/name=["\'](?:password|token|secret)["\']/i',$s));});

$add('all public UI assets are served through the stable gateway',function()use($root,$installer,$assert){if(!is_file($installer))throw new SkippedReleaseArtifact('Installer omitted from update package');
    foreach([$root.'/public/index.php',$installer,$root.'/public/update.php',$root.'/src/View/Renderer.php'] as $f){
        $source=file_get_contents($f);
        $assert(!str_contains($source,'/assets/'));
    }
    $renderer=file_get_contents($root.'/src/View/Renderer.php');
    $assert(str_contains($renderer,'/ui-asset.php?file=platform.css'));
    $assert(str_contains($renderer,'/ui-asset.php?file=brand-logo.png'));
    $css=file_get_contents($root.'/public/assets/platform.css');
    $assert(!str_contains($css,"url('/assets/"));
    $assert(is_file($root.'/public/ui-asset.php'));
});

$add('panel UI keeps the canonical shared component language',function()use($root,$assert){
    $css=file_get_contents($root.'/public/assets/panel.css');
    foreach(['--bn-primary:#1D3559','--bn-secondary:#D0E2F2','--bn-accent:#EDA900','.bn-brandbar{','.bn-card{','.bn-pagehead{','.bn-section{','.bn-stats{','.bn-login-grid{','.bn-footer{'] as $needle){$assert(str_contains($css,$needle),'missing shared token/component '.$needle);}
    $renderer=file_get_contents($root.'/src/View/Renderer.php');
    $version=require $root.'/config/version.php';
    $assert(str_contains($renderer,'function assetVersion(): string'));
    $assert(str_contains($renderer,'Ui::version()'));
    $assert(!str_contains($renderer,'private const PANEL_VERSION ='));
    $ui=file_get_contents($root.'/src/Platform/Ui.php');
    $assert(str_contains($ui,'bn-footer'));
});

$add('all visible panel timestamps use Persian formatter',function()use($root,$assert){
    $renderer=file_get_contents($root.'/src/View/Renderer.php');
    $assert(str_contains($renderer,'PersianDateTime::full'));
    $assert(str_contains($renderer,"new DateTimeZone('Asia/Tehran')")===false); // timezone belongs to formatter, not templates
    $date=file_get_contents($root.'/src/Support/PersianDateTime.php');
    $assert(str_contains($date,"new DateTimeZone('Asia/Tehran')"));
});



$add('release 1.2.1 fixes calculator integer parsing and archive UX',function()use($root,$assert){
    $m=file_get_contents($root.'/src/Loans/LoansModule.php');
    $assert(str_contains($m,'private function integerish(mixed $value): int'));
    $assert(str_contains($m,"preg_match('/^-?\\d+$/', \$x)"));
    $assert(str_contains($m,'جاری (بدون بایگانی)'));
    $assert(str_contains($m,'/loans/plans?status=archived'));
    $assert(str_contains($m,"['status'=>'active','per_page'=>100]"));
    $assert(str_contains($m,'تعداد اقساط باید یک عدد صحیح مثبت باشد.'));
});
$add('phase 2 panel wires Loans module and health dependency',function()use($root,$assert){
    $index=file_get_contents($root.'/public/index.php');
    $assert(str_contains($index,'new LoansModule'));
    $assert(str_contains($index,"'loans' => \$loansOk ? 'ok' : 'unavailable'"));
    $assert(str_contains($index,'$releaseVersion'));
    $assert(str_contains($index,'$uiVersion'));
    $assert(is_file($root.'/src/Http/LoansClient.php'));
    $assert(is_file($root.'/src/Loans/LoansModule.php'));
});
$add('phase 2 UI has loans responsive component layer',function()use($root,$assert){$css=file_get_contents($root.'/public/assets/panel.css');foreach(['/* Phase 2 — Loans module */','.bn-action-grid{','.bn-detail-grid{','.bn-filterbar{']as$n)$assert(str_contains($css,$n));});
$add('loans module escapes user visible detail values',function()use($root,$assert){$src=file_get_contents($root.'/src/Loans/LoansModule.php');$assert(str_contains($src,'Renderer::e($label)'));$assert(str_contains($src,'Renderer::e(PersianDateTime::digits($value))'));$assert(str_contains($src,'apiMessage('));});


$add('loans plan form switches engine-specific fields without inline styling',function()use($root,$assert){$m=file_get_contents($root.'/src/Loans/LoansModule.php');$js=file_get_contents($root.'/public/assets/panel.js');$assert(str_contains($m,'data-plan-type'));$assert(str_contains($m,'data-plan-fields="market"'));$assert(str_contains($m,'data-plan-fields="leasing"'));$assert(str_contains($js,'syncPlanType'));$assert(str_contains($js,"group.hidden=!active"));$assert(str_contains($js,"control.disabled=!active"));});
$add('loans module supports pagination draft edit guarantors history and transition feedback',function()use($root,$assert){$m=file_get_contents($root.'/src/Loans/LoansModule.php');foreach(['pagination(','requestEditForm(','saveGuarantors(','historySection(','eventsSection(','flash(','_action']as$n)$assert(str_contains($m,$n),'missing '.$n);});
$add('schedule uses date-only Jalali formatter and calculations use semantic formatting',function()use($root,$assert){$m=file_get_contents($root.'/src/Loans/LoansModule.php');$assert(str_contains($m,'PersianDateTime::dateOnly'));$assert(str_contains($m,'formatCalculation('));});
$add('shared component modifiers cover phase two forms and empty states',function()use($root,$assert){$css=file_get_contents($root.'/public/assets/panel.css');foreach(['.bn-grid--2','.bn-empty-inline','.bn-pagination','.bn-btn.secondary','.bn-btn--danger','.bn-checkline']as$n)$assert(str_contains($css,$n),'missing '.$n);});


$add('phase 3 panel wires Documents client module health and navigation',function()use($root,$assert){
    $index=file_get_contents($root.'/public/index.php');$renderer=file_get_contents($root.'/src/View/Renderer.php');
    foreach(['new DocumentsModule',"'documents' => \$documentsOk ? 'ok' : 'unavailable'",'href="/documents"'] as$n)$assert(str_contains($index.$renderer,$n),'missing '.$n);
    $assert(is_file($root.'/src/Http/DocumentsClient.php'));$assert(is_file($root.'/src/Documents/DocumentsModule.php'));
});
$add('phase 3 document module covers upload versions review archive audit and secure access',function()use($root,$assert){
    $m=file_get_contents($root.'/src/Documents/DocumentsModule.php');
    foreach(['/documents/files/','/replace','/review','/archive','access-token','Referrer-Policy: no-referrer','/api/v1/entities/loan_request/'] as$n)$assert(str_contains($m,$n),'missing '.$n);
    $assert(!str_contains($m,"name=\"visibility\""));
    $assert(str_contains($m,'data-max-bytes'));
});
$add('phase 3 upload limits are enforced in panel and client side',function()use($root,$assert,$eq){
    $eq(10485760,Babanoell\Panel\Support\IniSize::toBytes('10M'));
    $m=file_get_contents($root.'/src/Documents/DocumentsModule.php');$js=file_get_contents($root.'/public/assets/panel.js');
    $assert(str_contains($m,'requestExceedsPostLimit'));
    $assert(str_contains($m,'effectiveUploadLimit'));
    $assert(str_contains($js,'dataset.maxBytes'));
    $assert(str_contains($js,'file.size>limit'));
});
$add('phase 3 document categories render with Persian labels',function()use($root,$assert){
    $m=file_get_contents($root.'/src/Documents/DocumentsModule.php');
    foreach(['check_primary','check_secondary','credit_check','case_receipt','settlement_receipt'] as$n)$assert(str_contains($m,"'{$n}'"),'missing '.$n);
});
$add('phase 3 UI styles every Documents-specific component',function()use($root,$assert){
    $css=file_get_contents($root.'/public/assets/panel.css');
    foreach(['.bn-document-summary{','.bn-document-checklist{','.bn-upload-box{','.bn-file-error{','.bn-field-hint{','.bn-status--muted{','.bn-filterbar--documents{'] as$n)$assert(str_contains($css,$n),'missing '.$n);
});
$add('phase 3 plan forms expose required documents per immutable plan version',function()use($root,$assert){
    $m=file_get_contents($root.'/src/Loans/LoansModule.php');
    foreach(['required_documents[]','مدارک الزامی','guarantor_identity','contract'] as$n)$assert(str_contains($m,$n),'missing '.$n);
});
$add('phase 3 documents endpoint is configurable without secret database coupling',function()use($root,$assert){
    $cfg=file_get_contents($root.'/src/App/Config.php');$client=file_get_contents($root.'/src/Http/DocumentsClient.php');
    $assert(str_contains($cfg,'PANEL_DOCUMENTS_BASE_URL'));
    $assert(str_contains($cfg,'https://documents.noeel.ir'));
    $assert(!str_contains($client,'PDO'));
    $assert(!str_contains($cfg,'DOCUMENTS_DB_PASSWORD'));
});


$add('visible secondary actions use the canonical button modifier',function()use($root,$assert){
    foreach([$root.'/public/index.php',$root.'/src/Loans/LoansModule.php',$root.'/src/Documents/DocumentsModule.php'] as $file){
        $src=file_get_contents($file);
        $assert(!preg_match('/class=["\'][^"\']*(?:^|\s)secondary(?:\s|["\'])/m',$src),'legacy secondary class remains in '.basename($file));
    }
});

$add('phase 2A Panel propagates one correlation id to all service clients',function()use($assert,$eq){
    \Babanoell\Panel\Platform\RequestContext::set('req_phase2a_123456');
    $transport=new class implements \Babanoell\Panel\Http\HttpTransportInterface{public array$seen=[];public function request(string$method,string$url,array$headers=[],?string$body=null,int$timeoutSeconds=6):\Babanoell\Panel\Http\HttpResponse{$this->seen[]=$headers;return new \Babanoell\Panel\Http\HttpResponse(200,['status'=>'ok'],'');}};
    $cfg=panel_config();(new \Babanoell\Panel\Http\IdentityClient($cfg,$transport))->health();(new \Babanoell\Panel\Http\LoansClient($cfg,$transport))->health();(new \Babanoell\Panel\Http\DocumentsClient($cfg,$transport))->health();
    $eq(3,count($transport->seen));foreach($transport->seen as$h){$eq('req_phase2a_123456',$h['X-Request-Id']);$eq('panel',$h['X-Noel-Service']);}
});
$add('phase 2A Panel publishes boundary and supports optional HMAC service identity',function()use($root,$assert,$eq){
    $c=\Babanoell\Panel\Platform\ServiceBoundary::describe();$eq('panel',$c['service']);$assert(in_array('presentation',$c['owns'],true));
    $index=file_get_contents($root.'/public/index.php');$assert(str_contains($index,'/platform-contract'));$assert(str_contains($index,'X-Request-Id'));
    foreach(['IdentityClient.php','LoansClient.php','DocumentsClient.php']as$f)$assert(str_contains(file_get_contents($root.'/src/Http/'.$f),'ServiceAuth::outbound'));
});


$add('phase 2B wires a dedicated applicant self-service portal',function()use($root,$assert){
    $index=file_get_contents($root.'/public/index.php');$renderer=file_get_contents($root.'/src/View/Renderer.php');
    $assert(is_file($root.'/src/Applicant/ApplicantModule.php'));foreach(['new ApplicantModule','/applicant','$isApplicant']as$n)$assert(str_contains($index,$n),'missing '.$n);
    foreach(['پنل متقاضی','/applicant/calculator','/applicant/requests/new']as$n)$assert(str_contains($renderer,$n),'missing '.$n);
});
$add('phase 2B applicant creation is explicitly applicant-context and does not impersonate a merchant',function()use($root,$assert){
    $m=file_get_contents($root.'/src/Applicant/ApplicantModule.php');$assert(str_contains($m,"'plan_context'=>'applicant_request'"));
    $create=substr($m,strpos($m,'private function createRequest'),strpos($m,'private function requestEditForm')-strpos($m,'private function createRequest'));
    $assert(!str_contains($create,"'merchant_ref'=>"));$assert(str_contains($create,"/api/v1/requests"));
});
$add('phase 2B preserves stable guarantor ids in applicant acceptor and operations forms',function()use($root,$assert){
    foreach(['src/Applicant/ApplicantModule.php','src/Acceptor/AcceptorModule.php','src/Loans/LoansModule.php']as$f){$src=file_get_contents($root.'/'.$f);$assert(str_contains($src,'[id]'),$f.' missing guarantor id');$assert(str_contains($src,"\$g['id']"),$f.' missing guarantor value');}
});
$add('phase 2B panel uploads guarantor documents to the guarantor entity not the parent request',function()use($root,$assert){
    $m=file_get_contents($root.'/src/Documents/DocumentsModule.php');foreach(['loan_guarantor','uploadToGuarantor','guarantorCompletion',"str_starts_with((string)\$code,'guarantor_')"]as$n)$assert(str_contains($m,$n),'missing '.$n);
});


$add('phase 2B applicant and acceptor can submit saved drafts and resubmissions',function()use($root,$assert){
    foreach(['Applicant/ApplicantModule.php','Acceptor/AcceptorModule.php']as$f){$src=file_get_contents($root.'/src/'.$f);foreach(['/submit','submitRequest','ارسال برای بررسی','ارسال مجدد برای بررسی']as$n)$assert(str_contains($src,$n),$f.' missing '.$n);}
});
$add('phase 2B self service portals use context filtered plans and participant messages',function()use($root,$assert){
    $a=file_get_contents($root.'/src/Applicant/ApplicantModule.php');$m=file_get_contents($root.'/src/Acceptor/AcceptorModule.php');
    $assert(str_contains($a,"'context'=>'applicant_request'"));$assert(str_contains($m,"'context'=>'merchant_request'"));
    foreach([$a,$m]as$src){foreach(['messagesSection','sendMessage',"'visibility'=>'participants'"]as$n)$assert(str_contains($src,$n),'missing '.$n);}
});
$add('phase 2B document navigation returns to the correct role portal',function()use($root,$assert){
    $m=file_get_contents($root.'/src/Documents/DocumentsModule.php');foreach(['requestListPath','/applicant/requests','/acceptor/requests','requestDetailPath']as$n)$assert(str_contains($m,$n),'missing '.$n);
});


$add('phase 3 Panel wires Partners client module health and navigation',function()use($root,$assert){$index=file_get_contents($root.'/public/index.php');$renderer=file_get_contents($root.'/src/View/Renderer.php');foreach(['PartnersModule','partnersClient','/partners']as$n)$assert(str_contains($index.$renderer,$n));$assert(str_contains($index,'$partnersClient->health()'));$assert(str_contains($renderer,"\$item('partners','/partners'"));});
$add('phase 3 Panel supports merchants cooperation documents and marketplace',function()use($root,$assert){$m=file_get_contents($root.'/src/Partners/PartnersModule.php');foreach(['/partners/merchants','/partners/cooperation','/partners/marketplace','cooperation_request','plan-access']as$n)$assert(str_contains($m,$n));});
$add('phase 3 applicant request supports merchant selection or open marketplace',function()use($root,$assert){$m=file_get_contents($root.'/src/Applicant/ApplicantModule.php');foreach(['PartnersClient','acceptor_assignment_mode','open_acceptor_marketplace','merchant_id','request_public_summary']as$n)$assert(str_contains($m,$n));});
$add('phase 3 Panel configuration exposes Partners endpoint',function()use($root,$assert){$cfg=file_get_contents($root.'/src/App/Config.php');$assert(str_contains($cfg,'PANEL_PARTNERS_BASE_URL'));$assert(str_contains($cfg,'partnersBaseUrl'));});


$add('phase 3.0.1 sync result reports real imported counts and zero-source warning',function()use($root,$assert){$m=file_get_contents($root.'/src/Partners/PartnersModule.php');foreach(['sourceTotal','پذیرنده',"data']['import",'هیچ داده‌ای در Loans برای انتقال پیدا نشد'] as $n)$assert(str_contains($m,$n),'missing '.$n);});

$add('phase 4 Panel wires contract module for loan and cooperation subjects',function()use($root,$assert){$index=file_get_contents($root.'/public/index.php');$m=file_get_contents($root.'/src/Contracts/ContractsModule.php');foreach(['ContractsModule','new ContractsModule']as$n)$assert(str_contains($index,$n),$n);foreach(['/contracts/(loan|cooperation)','issue-link','sign-physical','artifact-retry']as$n)$assert(str_contains($m,$n),$n);});
$add('phase 4 plan editor exposes physical digital contract policy and versioned templates',function()use($root,$assert){$m=file_get_contents($root.'/src/Loans/LoansModule.php');foreach(['contract_mode','loan_application_contract_enabled','facility_agreement_contract_enabled','loan_application_template_code','facility_agreement_template_code']as$n)$assert(str_contains($m,$n),$n);});
$add('phase 4 request and cooperation detail link to contract engine',function()use($root,$assert){$l=file_get_contents($root.'/src/Loans/LoansModule.php');$p=file_get_contents($root.'/src/Partners/PartnersModule.php');$assert(str_contains($l,'/contracts/loan/'));$assert(str_contains($p,'/contracts/cooperation/'));});


$add('phase 5 Panel exposes a dedicated task inbox and request task link',function()use($root,$assert){$m=file_get_contents($root.'/src/Loans/LoansModule.php');$renderer=file_get_contents($root.'/src/View/Renderer.php');foreach(['/loans/tasks','کارتابل وظایف','وظایف پرونده','scope=mine','claim|start|complete|release|reassign|cancel']as$n)$assert(str_contains($m,$n),'missing '.$n);$assert(str_contains($renderer,'کارتابل من'));});
$add('phase 5 Panel includes SLA policy management and workflow report',function()use($root,$assert){$m=file_get_contents($root.'/src/Loans/LoansModule.php');foreach(['/loans/tasks/settings','/api/v1/tasks/sla/policies','apply_existing','/loans/tasks/report','/api/v1/tasks/report','عملکرد کارشناسان','زمان ماندگاری']as$n)$assert(str_contains($m,$n),'missing '.$n);});
$add('phase 5 Panel renders SLA and priority states responsively',function()use($root,$assert){$css=file_get_contents($root.'/public/assets/platform.css');foreach(['.bn-task-overdue{','.bn-task-priority--urgent{','.bn-task-actions{','.bn-task-reassign{']as$n)$assert(str_contains($css,$n),'missing '.$n);});


$add('phase 6 Panel wires dedicated bank portal and bank expert dashboard redirect',function()use($root,$assert){
    $index=file_get_contents($root.'/public/index.php');$renderer=file_get_contents($root.'/src/View/Renderer.php');$assert(is_file($root.'/src/Bank/BankModule.php'));foreach(['BankModule','new BankModule','$isBankExpert',"header('Location: /bank'",'/bank']as$n)$assert(str_contains($index,$n),'missing '.$n);foreach(['کارتابل بانک','loans.requests.bank_review',"\$item('bank','/bank'"]as$n)$assert(str_contains($renderer,$n),'missing '.$n);
});
$add('phase 6 bank portal covers inbox detail verification review and export',function()use($root,$assert){
    $m=file_get_contents($root.'/src/Bank/BankModule.php');foreach(['/api/v1/bank/summary','/api/v1/bank/cases','/api/v1/bank/export','/bank-credit/request','/bank-credit/result','/bank-review','/bank/export.csv','شاهکار','ics24']as$n)$assert(str_contains($m,$n),'missing '.$n);
});
$add('phase 6 bank portal uses idempotency for verification requests',function()use($root,$assert){$m=file_get_contents($root.'/src/Bank/BankModule.php');$assert(str_contains($m,'bin2hex(random_bytes(16))'));$assert(str_contains($m,"bank-credit/request"));});
$add('phase 6 plan UI exposes explicit Shahkar identity requirement',function()use($root,$assert){$m=file_get_contents($root.'/src/Loans/LoansModule.php');foreach(['requires_identity_verification','نیاز به تطبیق هویت شاهکار','تطبیق هویت شاهکار']as$n)$assert(str_contains($m,$n),'missing '.$n);});
$add('phase 6 bank portal hides verification override from normal bank experts',function()use($root,$assert){$m=file_get_contents($root.'/src/Bank/BankModule.php');foreach(['canOverrideVerification','loans.requests.workflow_admin','override_reason','Override مدیریتی']as$n)$assert(str_contains($m,$n),'missing '.$n);});
$add('phase 5 Bank workflow retains existing client contract with updated release metadata',function()use($root,$assert){$client=file_get_contents($root.'/src/Http/LoansClient.php');$version=require $root.'/config/version.php';$assert(str_contains($client,'BabanoellPanel/2.6.0')||str_contains($client,'BabanoellPanel/2.7.0'));$assert(version_compare((string)($version['version']??'0'),'2.12.0','>='));$assert(is_string($version['phase']??null) && trim($version['phase'])!=='');});


$add('phase 7 Panel wires Finance client health and active service boundary',function()use($root,$assert){
    $index=file_get_contents($root.'/public/index.php').file_get_contents($root.'/bootstrap.php');$cfg=file_get_contents($root.'/src/App/Config.php');$boundary=file_get_contents($root.'/src/Platform/ServiceBoundary.php');
    foreach(['FinanceClient','financeClient','/finance']as$n)$assert(str_contains($index.$cfg,$n),'missing '.$n);
    $assert(str_contains($cfg,'PANEL_FINANCE_BASE_URL'));$assert(str_contains($boundary,"'finance'=>'active'"));
});
$add('phase 7 Panel exposes finance dashboard cycles reconciliation and shadow import',function()use($root,$assert){
    $m=file_get_contents($root.'/src/Finance/FinanceModule.php');foreach(['/api/v1/stats','/api/v1/cycles','/api/v1/reconciliation','/api/v1/import/loans-shadow','finance.migration.manage','finance.cycles.manage']as$n)$assert(str_contains($m,$n),'missing '.$n);
});
$add('phase 7 navigation activates Finance for authorized roles',function()use($root,$assert){
    $r=file_get_contents($root.'/src/View/Renderer.php');foreach(['$canFinance',"\$item('finance','/finance'",'finance.dashboard.read']as$n)$assert(str_contains($r,$n),'missing '.$n);
});

$add('batch 1 panel exposes administrative correction forms with CSRF and reasons',function()use($root,$assert){foreach(['Loans/LoansModule.php','Partners/PartnersModule.php']as $f){$s=file_get_contents($root.'/src/'.$f);foreach(['profile-corrections','Csrf::token()','row_version','acknowledge_signed','reason']as $n)$assert(str_contains($s,$n),$f.': '.$n);}});
$add('batch 1 panel warns on failed shadow sync and provides explicit retry',function()use($root,$assert){$s=file_get_contents($root.'/src/Finance/FinanceModule.php');foreach(['shadow_sync','retry_required','resync-merchant-settlement','Csrf::token()','row_version','confirm']as $n)$assert(str_contains($s,$n),$n);});
$add('batch 2 panel provides revision editor with CSRF and protected print',function()use($root,$assert){$content=file_get_contents($root.'/src/Contracts/ContractsModule.php');foreach(['/reissue','reason','row_version','content_html','printContract','Csrf::valid','/api/v1/contracts/','Content-Security-Policy'] as $x)$assert(str_contains($content,$x),$x);});
$passed=0;$failed=0;$skipped=0;foreach($tests as $name=>$fn){try{$fn();echo "[PASS] $name\n";$passed++;}catch(SkippedReleaseArtifact $e){echo "[SKIP] $name: {$e->getMessage()}\n";$skipped++;}catch(Throwable $e){echo "[FAIL] $name: {$e->getMessage()}\n";$failed++;}}
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
echo "Panel tests: ".($passed+$failed)." run, $failed failed, $skipped skipped.\n"; $output=ob_get_clean(); fwrite(STDOUT,$output); exit($failed===0?0:1);
