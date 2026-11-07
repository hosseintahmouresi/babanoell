<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$fixture=json_decode((string)file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
if(!str_starts_with(basename($fixture['root']),'bb-e2e-')||!str_starts_with($fixture['socket'],$fixture['root'].'/'))throw new RuntimeException('Disposable fixture required');
$index=json_decode((string)file_get_contents($argv[2].'/release-index.json'),true,512,JSON_THROW_ON_ERROR);
$root=$fixture['root'];$names=['identity'=>'Auth','loans'=>'Loans','documents'=>'Documents','partners'=>'Partners','finance'=>'Finance','integrations'=>'Integrations','panel'=>'Panel'];
spl_autoload_register(static function(string $class)use($root,$names):void{foreach($names as $service=>$ns){$prefix='Babanoell\\'.$ns.'\\';if(str_starts_with($class,$prefix)){$file=$root.'/'.$service.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require $file;}}});
$pdo=static fn(string $service):PDO=>new PDO('mysql:unix_socket='.$fixture['socket'].';dbname=test_'.$service.';charset=utf8mb4',$fixture['db_user'],'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$packages=[];foreach($index['packages'] as $package)$packages[$package['service']]=$package;$result=[];
foreach($index['deployment_order'] as $service){
 $serviceRoot=$root.'/'.$service;$namespace='Babanoell\\'.$names[$service].'\\';$preserve=[];
 foreach(['config/local.env.php','config/service-auth.local.php','storage/release-canary.bin'] as $name){$file=$serviceRoot.'/'.$name;if(is_file($file))$preserve[$name]=hash_file('sha256',$file);}
 $zip=$serviceRoot.'/storage/update.zip';copy($argv[2].'/'.$packages[$service]['file'],$zip);
 if(!hash_equals($packages[$service]['sha256'],hash_file('sha256',$zip)))throw new RuntimeException('Package checksum mismatch');
 $tool=$namespace.'Update\\UpdateTool';$plan=$service==='identity'?$tool::readArchive($zip):(new $tool())->plan($zip);
 $transaction=$namespace.'Platform\\ReleaseTransaction';$migration=null;
 if($service==='integrations')$migration=static fn():array=>Babanoell\Integrations\Database\Migrator::up($pdo($service),$serviceRoot);
 elseif($service!=='panel'){$runner=$namespace.'Database\\MigrationRunner';$migration=static fn():array=>(new $runner($pdo($service),$serviceRoot.'/database/migrations'))->up();}
 $applied=$transaction::apply($zip,$serviceRoot,$serviceRoot.'/storage',hash_file('sha256',$zip),$migration);
 foreach($preserve as $name=>$hash)if(!hash_equals($hash,(string)hash_file('sha256',$serviceRoot.'/'.$name)))throw new RuntimeException('Protected bytes changed');
 $manifest=json_decode((string)file_get_contents($serviceRoot.'/release-manifest.json'),true,512,JSON_THROW_ON_ERROR);
 foreach($manifest['files'] as $name=>$hash)if(!hash_equals($hash,(string)hash_file('sha256',$serviceRoot.'/'.$name)))throw new RuntimeException('Deployed bytes differ from manifest');
 $rerun=$migration!==null?$migration():[];if($rerun!==[])throw new RuntimeException('Migrations are not idempotent');
 $version=require $serviceRoot.'/config/version.php';if(($version['ui']??null)!==$packages[$service]['ui'])throw new RuntimeException('Wrong deployed UI');
 if($transaction::pending($serviceRoot.'/storage'))throw new RuntimeException('Deployment journal remains pending');
 $result[]=['service'=>$service,'copied'=>$applied['copied'],'migrations_applied'=>count($applied['migrations']),'rerun_migrations'=>count($rerun),'protected_files_unchanged'=>count($preserve),'exact_manifest_bytes'=>true,'complete'=>true];
}
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
