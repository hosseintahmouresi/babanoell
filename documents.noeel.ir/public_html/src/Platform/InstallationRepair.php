<?php
declare(strict_types=1);
namespace Babanoell\Documents\Platform;

/** Finite, installation-bound repair. Never changes the main private configuration. */
final class InstallationRepair
{
    private const SERVICES=['loans','documents','finance','panel'];
    private const PREFIX="<?php /* Private service-auth override. */ exit; ?>\n";
    private const MAX_BYTES=8192;

    /** @return array{secret:string,status:string} */
    public static function resolve(string $root,string $service,#[\SensitiveParameter] array $local,#[\SensitiveParameter] string $effectiveSecret=''):array
    {
        if(trim($effectiveSecret)!=='')return ['secret'=>$effectiveSecret,'status'=>'existing'];
        if(!in_array($service,self::SERVICES,true))return self::empty('invalid_service');
        $directory=$root.'/config';$override=$directory.'/service-auth.local.php';
        if(!is_dir($directory)||is_link($directory)||is_link($override))return self::empty('unsafe_path');
        if(file_exists($override))return self::stored($override,$service);
        $path=$root.'/src/Platform/installation-repair-envelope.json';
        if(!is_file($path))return self::empty('not_requested');
        if(is_link($path)||filesize($path)>self::MAX_BYTES)return self::empty('capsule_invalid');
        try{$envelope=json_decode((string)file_get_contents($path),true,16,JSON_THROW_ON_ERROR);}catch(\Throwable){return self::empty('capsule_invalid');}
        if($envelope===[])return self::empty('not_requested');
        if(!self::validEnvelope($envelope,$service))return self::empty('capsule_invalid');
        if(!function_exists('sodium_crypto_pwhash')||!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt'))return self::empty('crypto_unavailable');
        $lockPath=$directory.'/.service-auth-repair.lock';
        if(is_link($lockPath))return self::empty('unsafe_path');
        $mask=umask(0077);try{$lock=@fopen($lockPath,'c+b');}finally{umask($mask);}
        if($lock===false)return self::empty('write_unavailable');
        $temporary=null;
        try{
            if(!flock($lock,LOCK_EX))return self::empty('write_unavailable');
            if(is_link($override))return self::empty('unsafe_path');
            if(file_exists($override))return self::stored($override,$service);
            try{$secret=self::open($service,$local,$envelope);}catch(\Throwable){return self::empty('capsule_unavailable');}
            if($secret===null)return self::empty('capsule_unavailable');
            $bytes=self::PREFIX.json_encode(['schema'=>1,'service'=>$service,'secret'=>$secret,'repaired_at'=>gmdate('c')],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
            $temporary=$directory.'/.service-auth-repair-'.bin2hex(random_bytes(12)).'.tmp';
            $mask=umask(0077);try{$handle=@fopen($temporary,'x+b');}finally{umask($mask);}
            if($handle===false)return self::empty('write_unavailable');
            try{
                if(!@chmod($temporary,0600))return self::empty('write_unavailable');
                clearstatcache(true,$temporary);
                if(PHP_OS_FAMILY!=='Windows'&&((fileperms($temporary)&0077)!==0))return self::empty('write_unavailable');
                if(fwrite($handle,$bytes)!==strlen($bytes)||!fflush($handle))return self::empty('write_unavailable');
                if(function_exists('fsync')&&!fsync($handle))return self::empty('write_unavailable');
            }finally{fclose($handle);}
            // Atomic no-clobber publication: readers never see a partial data file.
            if(!function_exists('link')||!@link($temporary,$override)){
                if(is_link($override))return self::empty('unsafe_path');
                return file_exists($override)?self::stored($override,$service):self::empty('write_unavailable');
            }
            return ['secret'=>$secret,'status'=>'repaired'];
        }catch(\Throwable){return self::empty('write_unavailable');}
        finally{if(is_string($temporary)&&is_file($temporary))@unlink($temporary);flock($lock,LOCK_UN);fclose($lock);}
    }

    /** Build-time API; caller must keep installation inputs private. */
    public static function seal(string $service,#[\SensitiveParameter] array $local,#[\SensitiveParameter] string $secret):array
    {
        if(!in_array($service,self::SERVICES,true)||strlen(trim($secret))<32||strlen($secret)>512)throw new \RuntimeException('Repair inputs are not suitable.');
        $material=self::material($service,$local);$salt=random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);$nonce=random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);$key=null;
        try{$key=self::derive($material,$salt);$cipher=sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($secret,self::aad($service),$nonce,$key);}
        finally{if(is_string($key))sodium_memzero($key);sodium_memzero($material);}
        return ['schema'=>1,'service'=>$service,'salt'=>base64_encode($salt),'nonce'=>base64_encode($nonce),'ciphertext'=>base64_encode($cipher)];
    }

    private static function validEnvelope(mixed $data,string $service):bool
    {
        if(!is_array($data))return false;$keys=array_keys($data);sort($keys);
        if($keys!==['ciphertext','nonce','salt','schema','service']||$data['schema']!==1||$data['service']!==$service)return false;
        foreach(['salt'=>16,'nonce'=>24]as$key=>$length)if(!is_string($data[$key])||strlen((string)base64_decode($data[$key],true))!==$length)return false;
        if(!is_string($data['ciphertext']))return false;$cipher=base64_decode($data['ciphertext'],true);
        return is_string($cipher)&&strlen($cipher)>=48&&strlen($cipher)<=528;
    }

    private static function open(string $service,#[\SensitiveParameter] array $local,array $data):?string
    {
        $material=self::material($service,$local);$key=null;
        try{
            $key=self::derive($material,(string)base64_decode($data['salt'],true));
            $secret=sodium_crypto_aead_xchacha20poly1305_ietf_decrypt((string)base64_decode($data['ciphertext'],true),self::aad($service),(string)base64_decode($data['nonce'],true),$key);
            return is_string($secret)&&strlen(trim($secret))>=32&&strlen($secret)<=512?$secret:null;
        }finally{if(is_string($key))sodium_memzero($key);sodium_memzero($material);}
    }

    private static function material(string $service,#[\SensitiveParameter] array $local):string
    {
        $prefix=strtoupper($service);$anchor=$local[$service==='panel'?'PANEL_SESSION_ENCRYPTION_KEY':$prefix.'_DB_PASSWORD']??null;
        if(!is_string($anchor)||strlen(trim($anchor))<16||preg_match('/change.?me|example|password|replace/i',$anchor))throw new \RuntimeException('Installation anchor is not suitable.');
        return json_encode(['schema'=>1,'service'=>$service,'anchor'=>$anchor,'database'=>$service==='panel'?'':(string)($local[$prefix.'_DB_NAME']??''),'user'=>$service==='panel'?'':(string)($local[$prefix.'_DB_USER']??'')],JSON_THROW_ON_ERROR);
    }

    private static function derive(#[\SensitiveParameter] string $material,string $salt):string
    {
        return sodium_crypto_pwhash(32,$material,$salt,SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
    }
    private static function aad(string $service):string{return 'noel-installation-service-auth-repair:v1:'.$service;}
    private static function empty(string $status):array{return ['secret'=>'','status'=>$status];}
    private static function stored(string $path,string $service):array
    {
        if(!is_file($path)||is_link($path)||filesize($path)>self::MAX_BYTES)return self::empty('override_conflict');
        $raw=@file_get_contents($path);if(!is_string($raw)||!str_starts_with($raw,self::PREFIX))return self::empty('override_conflict');
        try{$value=json_decode(substr($raw,strlen(self::PREFIX)),true,8,JSON_THROW_ON_ERROR);}catch(\Throwable){return self::empty('override_conflict');}
        if(!is_array($value)||($value['schema']??null)!==1||($value['service']??null)!==$service||!is_string($value['secret']??null)||strlen(trim($value['secret']))<32||strlen($value['secret'])>512)return self::empty('override_conflict');
        return ['secret'=>$value['secret'],'status'=>'stored'];
    }
}
