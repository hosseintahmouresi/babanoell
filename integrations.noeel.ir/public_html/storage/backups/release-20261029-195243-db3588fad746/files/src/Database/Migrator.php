<?php
declare(strict_types=1);
namespace Babanoell\Integrations\Database;
final class Migrator {
 public static function status(\PDO $pdo,string $root):array {
  $files=glob($root.'/database/migrations/*.sql')?:[];sort($files,SORT_STRING);$done=[];
  try{$rows=$pdo->query('SELECT name FROM integration_schema_migrations')->fetchAll(\PDO::FETCH_COLUMN);$done=array_fill_keys($rows,true);}catch(\PDOException){/* First installation only */}
  $out=[];foreach($files as $file){$name=basename($file);$out[]=['name'=>$name,'status'=>isset($done[$name])?'applied':'pending'];}return ['migrations'=>$out];
 }
 public static function up(\PDO $pdo,string $root):array {
  $pdo->exec('CREATE TABLE IF NOT EXISTS integration_schema_migrations (name VARCHAR(120) NOT NULL PRIMARY KEY,applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  $done=[];$files=glob($root.'/database/migrations/*.sql')?:[];sort($files,SORT_STRING);
  foreach($files as $file){$name=basename($file);$q=$pdo->prepare('SELECT COUNT(*) FROM integration_schema_migrations WHERE name=:n');$q->execute([':n'=>$name]);if((int)$q->fetchColumn()>0)continue;
   $sql=(string)file_get_contents($file);$statements=preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[];
   foreach($statements as $statement){$statement=trim($statement);if($statement!=='')$pdo->exec($statement);}
   $q=$pdo->prepare('INSERT INTO integration_schema_migrations(name) VALUES(:n)');$q->execute([':n'=>$name]);$done[]=$name;
  }
  return $done;
 }
}
