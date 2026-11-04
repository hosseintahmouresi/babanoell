<?php
declare(strict_types=1);
use Babanoell\Partners\Database\Migration;
use Babanoell\Partners\Domain\CooperationFormDefinition;
return new class implements Migration {
    public function version():string{return '202611030001';}
    public function name():string{return 'cooperation_form_revisions';}
    public function up(PDO $pdo):void{
        $pdo->exec("CREATE TABLE IF NOT EXISTS cooperation_form_revisions (revision INT UNSIGNED NOT NULL PRIMARY KEY,definition_json LONGTEXT NOT NULL,updated_by_user_id CHAR(26) NULL,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $s=$pdo->prepare('INSERT IGNORE INTO cooperation_form_revisions(revision,definition_json) VALUES(1,?)');
        $s->execute([json_encode(CooperationFormDefinition::defaults(),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    }
};
