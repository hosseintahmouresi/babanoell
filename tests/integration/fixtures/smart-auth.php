<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$f=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);if(!str_starts_with(basename($f['root']),'bb-e2e-')||!str_starts_with($f['socket'],$f['root'].'/'))throw new RuntimeException('Disposable fixture required');
$root=$f['root'].'/identity';$pdo=new PDO('mysql:unix_socket='.$f['socket'].';dbname=test_identity;charset=utf8mb4',$f['db_user'],'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);$pdo->exec("SET SESSION time_zone='+00:00'");$action=$argv[2];$b=json_decode($argv[3]??'{}',true,512,JSON_THROW_ON_ERROR);
if($action==='configure'){$path=$root.'/config/local.env.php';$c=require $path;$c['INTEGRATIONS_BASE_URL']=$b['base'];file_put_contents($path,'<?php return '.var_export($c,true).';');chmod($path,0600);echo '{}';}
elseif($action==='seed'){
 $role=$pdo->query("SELECT id FROM roles WHERE name='applicant'")->fetchColumn();$out=[];
 foreach(['smart.nohash'=>'09387777001','smart.disabled'=>'09387777002','smart.locked'=>'09387777003','smart.changed'=>'09387777004','smart.expires'=>'09387777005'] as $username=>$mobile){$id=bin2hex(random_bytes(13));$q=$pdo->prepare("INSERT INTO users (id,username,mobile,display_name,status,password_hash,disabled_at,locked_until) VALUES (?,?,?,'Synthetic smart account',?,NULL,?,?)");$disabled=$username==='smart.disabled';$q->execute([$id,$username,$mobile,$disabled?'disabled':'active',$disabled?gmdate('Y-m-d H:i:s'):null,$username==='smart.locked'?gmdate('Y-m-d H:i:s',time()+600):null]);$q=$pdo->prepare('INSERT INTO user_roles (user_id,role_id) VALUES (?,?)');$q->execute([$id,$role]);$out[$username]=['id'=>$id,'mobile'=>$mobile];}echo json_encode($out);
}
elseif($action==='cooldown'){$pdo->exec('UPDATE auth_smart_limits SET expires_at=0 WHERE window_start=0');echo '{}';}
elseif($action==='expire'){$q=$pdo->prepare('UPDATE auth_phone_challenges SET expires_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=?');$q->execute([$b['id']]);echo '{}';}
elseif($action==='password'){$q=$pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');$q->execute([password_hash('synthetic changed password',PASSWORD_ARGON2ID),$b['id']]);echo '{}';}
elseif($action==='inspect'){
 $q=$pdo->prepare('SELECT u.id,u.username,u.mobile,u.status,u.password_hash,u.mobile_verified_at,(SELECT GROUP_CONCAT(r.name ORDER BY r.name) FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=u.id) roles FROM users u WHERE mobile=?');$q->execute([$b['mobile']]);$u=$q->fetch(PDO::FETCH_ASSOC);if($u){$u['has_password']=trim((string)$u['password_hash'])!=='';unset($u['password_hash']);}
 $q=$pdo->prepare('SELECT code_hash,registration_json,attempts,send_count,consumed_at,delivery_status FROM auth_phone_challenges WHERE id=?');$q->execute([$b['challenge_id']??'']);$c=$q->fetch(PDO::FETCH_ASSOC);if($c){$c['hash_only']=preg_match('/^[a-f0-9]{64}$/D',$c['code_hash'])===1;$c['consumed']=$c['consumed_at']!==null;$c['registration_cleared']=$c['registration_json']===null;unset($c['code_hash'],$c['registration_json'],$c['consumed_at']);}
 echo json_encode(['user'=>$u?:null,'challenge'=>$c?:null]);
}
