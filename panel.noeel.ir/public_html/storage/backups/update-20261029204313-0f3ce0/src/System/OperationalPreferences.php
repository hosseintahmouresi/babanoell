<?php
declare(strict_types=1);
namespace Babanoell\Panel\System;

/** Allow-listed, non-secret UI policy. This never modifies another service's private configuration. */
final class OperationalPreferences
{
    public const DEFAULTS = [
        'report_default_days'=>30,
        'report_max_days'=>90,
        'health_slow_threshold_ms'=>1500,
    ];
    private readonly string $dir;
    public function __construct(string $root)
    {
        $this->dir = rtrim($root, '/') . '/storage/operational-preferences';
    }
    public function get(): array
    {
        $path=$this->dir.'/settings.json';
        if(!is_file($path)) return self::DEFAULTS+['revision'=>0];
        $decoded=json_decode((string)@file_get_contents($path),true);
        if(!is_array($decoded)) throw new \RuntimeException('Invalid stored operational preferences');
        $values=[];
        foreach(self::DEFAULTS as $name=>$default){
            $value=$decoded[$name]??$default;
            $values[$name]=is_int($value)?$value:$default;
        }
        return $values+['revision'=>max(0,(int)($decoded['revision']??0))];
    }
    public static function validate(array $input): array
    {
        foreach(array_keys(self::DEFAULTS) as $key){
            if(!array_key_exists($key,$input))throw new \InvalidArgumentException('missing_'.$key);
        }
        if(count(array_diff(array_keys($input),array_keys(self::DEFAULTS)))>0)throw new \InvalidArgumentException('unknown_setting');
        $day=(string)$input['report_default_days'];
        $maximum=(string)$input['report_max_days'];
        $slow=(string)$input['health_slow_threshold_ms'];
        if(!in_array($day,['7','14','30','60','90'],true))throw new \InvalidArgumentException('invalid_default_days');
        if(!in_array($maximum,['30','90','180'],true))throw new \InvalidArgumentException('invalid_max_days');
        if(!preg_match('/^[0-9]{3,5}$/D',$slow)||(int)$slow<500||(int)$slow>10000)throw new \InvalidArgumentException('invalid_health_threshold');
        if((int)$day>(int)$maximum)throw new \InvalidArgumentException('default_exceeds_max');
        return ['report_default_days'=>(int)$day,'report_max_days'=>(int)$maximum,'health_slow_threshold_ms'=>(int)$slow];
    }
    public function save(array $input,int $expectedRevision,string $actor): array
    {
        $new=self::validate($input);
        if(!preg_match('/^[a-zA-Z0-9_-]{1,64}$/D',$actor))throw new \InvalidArgumentException('invalid_actor');
        if(!is_dir($this->dir)&&!@mkdir($this->dir,0700,true)&&!is_dir($this->dir))throw new \RuntimeException('Settings directory not writable');
        @chmod($this->dir,0700);
        $lock=@fopen($this->dir.'/settings.lock','c');
        if($lock===false)throw new \RuntimeException('Settings lock unavailable');
        try{
            if(!flock($lock,LOCK_EX))throw new \RuntimeException('Cannot acquire settings lock');
            $old=$this->get();
            if($old['revision']!==$expectedRevision)throw new \DomainException('settings_changed_conflict');
            $new['revision']=$expectedRevision+1;
            $encoded=json_encode($new,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            $temp=@tempnam($this->dir,'.settings-');
            if($temp===false)throw new \RuntimeException('Settings staging unavailable');
            try{
                if(file_put_contents($temp,$encoded,LOCK_EX)!==strlen($encoded))throw new \RuntimeException('Settings write failed');
                @chmod($temp,0600);
                // The audit trail is required: refuse to save if it cannot be written.
                $audit=json_encode(['at'=>gmdate('c'),'actor'=>$actor,'previous'=>$old,'current'=>$new],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
                $a=@fopen($this->dir.'/audit.jsonl','ab');
                if($a===false)throw new \RuntimeException('Audit log unavailable');
                try{if(fwrite($a,$audit)!==strlen($audit)||!fflush($a))throw new \RuntimeException('Audit log write failed');}finally{fclose($a);}
                @chmod($this->dir.'/audit.jsonl',0600);
                if(!@rename($temp,$this->dir.'/settings.json'))throw new \RuntimeException('Settings atomic replace failed');
                return $new;
            }finally{if(is_file($temp))@unlink($temp);}
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public function history(int $limit=10): array
    {
        $file=$this->dir.'/audit.jsonl';
        if(!is_file($file))return [];
        $lines=@file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
        if(!is_array($lines))return [];
        $records=[];
        foreach(array_reverse(array_slice($lines,-max(1,min(20,$limit)))) as $line){
            $decoded=json_decode($line,true);
            if(is_array($decoded))$records[]=$decoded;
        }
        return $records;
    }
}
