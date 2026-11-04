<?php
declare(strict_types=1);
namespace Babanoell\Partners\Repository;
use Babanoell\Partners\Domain\CooperationFormDefinition;
use PDO;
use RuntimeException;
use Throwable;

final class CooperationFormRepository
{
    public function __construct(private readonly PDO $pdo){}
    public function current():array
    {
        $row=$this->pdo->query('SELECT revision,definition_json FROM cooperation_form_revisions ORDER BY revision DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if(!is_array($row))throw new RuntimeException('cooperation_definition_invalid');
        $definition=json_decode((string)$row['definition_json'],true,512,JSON_THROW_ON_ERROR);
        if(!is_array($definition))throw new RuntimeException('cooperation_definition_invalid');
        return ['revision'=>(int)$row['revision']]+$definition;
    }
    public function save(string$actor,int$expected,array$input):array
    {
        $this->pdo->beginTransaction();
        try{
            $row=$this->pdo->query('SELECT revision,definition_json FROM cooperation_form_revisions ORDER BY revision DESC LIMIT 1 FOR UPDATE')->fetch(PDO::FETCH_ASSOC);
            if(!is_array($row)||(int)$row['revision']!==$expected)throw new RuntimeException('form_revision_conflict');
            $current=json_decode((string)$row['definition_json'],true,512,JSON_THROW_ON_ERROR);
            $definition=CooperationFormDefinition::configure($current,$input);$revision=$expected+1;
            $s=$this->pdo->prepare('INSERT INTO cooperation_form_revisions(revision,definition_json,updated_by_user_id) VALUES(?,?,?)');
            $s->execute([$revision,json_encode($definition,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$actor]);
            (new PdoPartnerRepository($this->pdo))->event('cooperation_form',null,'cooperation.form.published',$actor,'allowed',['previous_revision'=>$expected,'revision'=>$revision]);
            $this->pdo->commit();return['revision'=>$revision]+$definition;
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();if($e instanceof \PDOException&&in_array($e->getCode(),['23000','40001'],true))throw new RuntimeException('form_revision_conflict',0,$e);throw$e;}
    }
}
