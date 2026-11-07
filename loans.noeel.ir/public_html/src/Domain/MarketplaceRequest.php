<?php
declare(strict_types=1);
namespace Babanoell\Loans\Domain;
use InvalidArgumentException;

final class MarketplaceRequest
{
 public static function validate(array $input,array $catalog):void
 {
  if(($catalog['enabled']??false)!==true)throw new InvalidArgumentException('بازار پذیرندگان در حال حاضر غیرفعال است.');
  $scope=trim((string)($input['requested_service_scope']??'all_country'));
  if(!in_array($scope,['all_country','selected_locations','selected_cities'],true))throw new InvalidArgumentException('محدوده خدمت درخواست معتبر نیست.');
  if($scope==='all_country'&&empty($catalog['allow_all_country']))throw new InvalidArgumentException('برای این درخواست موقعیت خدمت را انتخاب کنید.');
  foreach(['requested_activity_categories'=>'categories','requested_location_terms'=>'locations','requested_cities'=>'locations']as$key=>$kind){
   $ids=$input[$key]??[];if(!is_array($ids)||count($ids)>50)throw new InvalidArgumentException('حداکثر ۵۰ حوزه فعالیت یا موقعیت انتخاب کنید.');
   $allowed=array_column(array_filter((array)($catalog[$kind]??[]),static fn($term)=>is_array($term)&&($term['active']??false)===true),'id');
   foreach($ids as$id)if(!is_string($id)||!in_array($id,$allowed,true))throw new InvalidArgumentException('حوزه فعالیت یا موقعیت انتخاب‌شده در فهرست فعال بازار وجود ندارد.');
  }
  if(!empty($catalog['require_category'])&&empty($input['requested_activity_categories']))throw new InvalidArgumentException('حوزه فعالیت درخواست را انتخاب کنید.');
  if($scope!=='all_country'&&!empty($catalog['require_location'])&&empty($input['requested_location_terms'])&&empty($input['requested_cities']))throw new InvalidArgumentException('موقعیت خدمت درخواست را انتخاب کنید.');
 }
}
