<?php
declare(strict_types=1);
$root = dirname(__DIR__);
spl_autoload_register(static function(string $class) use ($root): void {
    $prefix='Babanoell\\Documents\\';
    if(str_starts_with($class,$prefix)){
        $file=$root.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
        if(is_file($file))require $file;
    }
});
$cases=[];
function ensure(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function scenario(string $name,callable $f):void{global $cases;$cases[$name]=$f;}
scenario('document catalog keeps all legacy codes and new note attachment confidential',static function():void{
    $legacy=['national_card','birth_certificate','primary_check','secondary_check','signed_contract','invoice','settlement_receipt','bank_settlement_receipt','cooperation_contract'];
    $catalog=\Babanoell\Documents\Domain\DocumentCatalog::all();
    foreach($legacy as $code)ensure(isset($catalog[$code]),$code);
    ensure(($catalog['internal_note_attachment']['visibility']??null)==='confidential','note attachment visibility');
});
scenario('private storage creates deny files and preserves explicit policy', static function():void{
    $base=sys_get_temp_dir().'/noel-11f-docs-'.bin2hex(random_bytes(6));
    try {
        $storage=new \Babanoell\Documents\Storage\PrivateStorage($base);
        ensure(str_contains(file_get_contents($base.'/.htaccess'),'Require all denied'),'base is not blocked');
        $original="Require all denied\n# site-specific policy\n";
        file_put_contents($base.'/.htaccess',$original);
        new \Babanoell\Documents\Storage\PrivateStorage($base);
        ensure(file_get_contents($base.'/.htaccess')===$original,'existing policy overwritten');
        $temp=tempnam(sys_get_temp_dir(),'noel11f');file_put_contents($temp,"%PDF-1.4\n%%EOF\n");
        try{
            $validated=(new \Babanoell\Documents\Domain\UploadValidator(1024*1024))->validate(['name'=>'sample.pdf','tmp_name'=>$temp,'size'=>filesize($temp),'error'=>UPLOAD_ERR_OK],false);
            $key=$storage->store($validated);
            ensure(str_contains(file_get_contents($base.'/'.dirname($key).'/.htaccess'),'Require all denied'),'monthly directory not blocked');
            ensure(is_file($storage->path($key)),'stored file unavailable');
        }finally{@unlink($temp);}
    } finally {
        if(is_dir($base)){
            $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
            foreach($it as $f){$f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname());}
            @rmdir($base);
        }
    }
});
scenario('Loans client reports installed Documents version',static function():void {
    $received=[];
    $transport=new class($received) implements \Babanoell\Documents\Http\HttpTransportInterface {
        public array $seen=[];
        public function __construct(array &$received){$this->seen=&$received;}
        public function request(string $method,string $url,array $headers=[],?string $body=null,int $timeoutSeconds=6):\Babanoell\Documents\Http\HttpResponse{
            $this->seen=$headers;
            return new \Babanoell\Documents\Http\HttpResponse(200,['status'=>'ok'],'');
        }
    };
    $cfg=new \Babanoell\Documents\App\Config('documents','staging','localhost',3306,'db','u','p','utf8mb4','https://identity.test','https://loans.test');
    $client=new \Babanoell\Documents\Http\LoansClient($cfg,$transport);
    $client->health();$version=require dirname(__DIR__).'/config/version.php';
    ensure(($received['User-Agent']??null)==='BabanoellDocuments/'.$version['version'],'client has stale User-Agent');
});
$passed=0;$failed=0;
foreach($cases as $name=>$f){try{$f();echo "[PASS] $name\n";$passed++;}catch(Throwable $e){echo "[FAIL] $name: {$e->getMessage()}\n";$failed++;}}
echo "Phase11F Documents: $passed pass, $failed fail\n";
exit($failed?1:0);
