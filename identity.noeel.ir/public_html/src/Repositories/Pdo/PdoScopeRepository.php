<?php
declare(strict_types=1);
namespace Babanoell\Auth\Repositories\Pdo;
use Babanoell\Auth\Authorization\ScopeRepositoryInterface; use PDO;
final class PdoScopeRepository implements ScopeRepositoryInterface {
 public function __construct(private readonly PDO $pdo){}
 public function forUser(string $userId): array {
  $m=$this->pdo->prepare("SELECT m.organization_id,m.branch_id,m.title,m.is_primary,m.attributes_json,o.code AS organization_code,o.name AS organization_name,o.organization_type,b.code AS branch_code,b.name AS branch_name FROM user_organization_memberships m JOIN organizations o ON o.id=m.organization_id LEFT JOIN branches b ON b.id=m.branch_id WHERE m.user_id=:uid AND m.membership_status='active' AND o.status='active' ORDER BY m.is_primary DESC,o.name,b.name");$m->execute([':uid'=>$userId]);$members=[];foreach($m->fetchAll(PDO::FETCH_ASSOC) as$r){$r['attributes']=$this->json($r['attributes_json']??null);unset($r['attributes_json']);$r['is_primary']=(bool)$r['is_primary'];$members[]=$r;}
  $g=$this->pdo->prepare("SELECT scope_type,scope_id,effect,attributes_json,expires_at FROM authorization_scope_grants WHERE user_id=:uid AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP(6)) ORDER BY scope_type,scope_id,effect");$g->execute([':uid'=>$userId]);$grants=[];foreach($g->fetchAll(PDO::FETCH_ASSOC) as$r){$r['attributes']=$this->json($r['attributes_json']??null);unset($r['attributes_json']);$grants[]=$r;}
  return ['memberships'=>$members,'grants'=>$grants];
 }
 private function json(mixed $v): array { if(!is_string($v)||$v==='')return[];$d=json_decode($v,true);return is_array($d)?$d:[]; }
}
