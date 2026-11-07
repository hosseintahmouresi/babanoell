<?php
declare(strict_types=1);
namespace Babanoell\Loans\Domain;

/** One predicate is shared by paginated visibility and the atomic claim. */
final class MarketplaceEligibility
{
 public static function sql(array $context):array
 {
  if(($context['enabled']??false)!==true)return ['0=1',[]];
  $params=[];
  $categories="COALESCE(r.requested_activity_categories_json,JSON_ARRAY())";
  $locations="CASE WHEN JSON_LENGTH(COALESCE(r.requested_location_terms_json,JSON_ARRAY()))>0 THEN r.requested_location_terms_json ELSE COALESCE(r.requested_cities_json,JSON_ARRAY()) END";
  $where=["p.status='active'"];
  $where[]=(!empty($context['require_category'])?'':'JSON_LENGTH('.$categories.')=0 OR ').self::any($categories,(array)($context['category_ids']??[]),'category',$params);
  $locationMatch=(!empty($context['require_location'])?'':'JSON_LENGTH('.$locations.')=0 OR ').self::any($locations,(array)($context['location_ids']??[]),'location',$params);
  $where[]="((r.requested_service_scope IN ('selected_locations','selected_cities') AND (".$locationMatch."))".
   (!empty($context['allow_all_country'])?" OR COALESCE(r.requested_service_scope,'') IN ('','all_country')":'').')';
  $max=(float)($context['max_facility_amount']??0);
  if($max>0){$where[]='COALESCE(r.facility_amount,r.requested_amount)<=:market_max';$params[':market_max']=$max;}
  $active=[];$inactive=[];
  foreach((array)($context['plan_permissions']??[])as$row){
   if(!is_array($row)||!preg_match('/^[a-f0-9]{26}$/D',(string)($row['plan_id']??'')))continue;
   if(($row['status']??'')==='active')$active[]=$row['plan_id'];else $inactive[]=$row['plan_id'];
  }
  // Explicit revocation overrides the default; allowlists require an active grant.
  $where[]="JSON_CONTAINS(:market_revoked,JSON_QUOTE(r.plan_id))=0";
  $where[]="(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(v.rules_json,'$.merchant_access_mode')),'all')<>'allowlist' OR JSON_CONTAINS(:market_allowed,JSON_QUOTE(r.plan_id))=1)";
  $params[':market_revoked']=json_encode($inactive,JSON_THROW_ON_ERROR);$params[':market_allowed']=json_encode($active,JSON_THROW_ON_ERROR);
  return ['('.implode(') AND (',$where).')',$params];
 }
 private static function any(string $expression,array $values,string $prefix,array &$params):string
 {
  $parts=[];
  foreach(array_values(array_unique($values))as$i=>$value){$key=':market_'.$prefix.'_'.$i;$params[$key]=json_encode((string)$value,JSON_THROW_ON_ERROR);$parts[]='JSON_CONTAINS('.$expression.','.$key.')=1';}
  return $parts===[]?'0=1':'('.implode(' OR ',$parts).')';
 }
}
