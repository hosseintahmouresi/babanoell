<?php
declare(strict_types=1);
namespace Babanoell\Integrations\Support;
final class Config {
 public static function get(string $key,string $default=''):string {return \noeel_integrations_env($key,$default);}
 public static function production():bool{return self::get('APP_ENV','production')==='production';}
 public static function db():\PDO {
  $host=self::get('INTEGRATIONS_DB_HOST','localhost');$port=(int)self::get('INTEGRATIONS_DB_PORT','3306');
  $name=self::get('INTEGRATIONS_DB_NAME');$user=self::get('INTEGRATIONS_DB_USER');$pass=self::get('INTEGRATIONS_DB_PASSWORD');
  if(!preg_match('/^[A-Za-z0-9_.-]{1,80}$/',$host)||$port<1||$port>65535||!preg_match('/^[A-Za-z0-9_]{1,64}$/',$name)||$user==='')throw new \RuntimeException('database_configuration_missing');
  return new \PDO('mysql:host='.$host.';port='.$port.';dbname='.$name.';charset=utf8mb4',$user,$pass,[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC,\PDO::ATTR_EMULATE_PREPARES=>false]);
 }
 public static function requireSecret():string{$s=self::get('NOEEL_SERVICE_SHARED_SECRET');if(strlen($s)<32)throw new \RuntimeException('shared_secret_missing');return $s;}
}
