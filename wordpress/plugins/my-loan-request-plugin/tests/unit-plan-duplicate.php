<?php
$passed=0; $failed=0;
function t($name,$ok){global $passed,$failed;if($ok){$passed++;echo "PASS: $name\n";}else{$failed++;echo "FAIL: $name\n";}}
function duplicate_row(array $source,string $name,string $createdAt,int $maxOrder): array {
    $out=$source;
    unset($out['id']);
    $out['name']=$name;
    $out['is_active']=0;
    $out['created_at']=$createdAt;
    if(array_key_exists('display_order',$out)) $out['display_order']=$maxOrder+10;
    return $out;
}
$source=[
    'id'=>44,'name'=>'طرح ویژه','type'=>'leasing','is_active'=>1,'display_order'=>30,
    'settings'=>'SERIALIZED_SETTINGS','display_fields'=>'FIELDS','display_fields_context'=>'CONTEXT',
    'required_documents'=>'DOCS','created_at'=>'2026-01-01 10:00:00'
];
$copy=duplicate_row($source,'طرح ویژه — کپی','2026-09-09 00:00:00',70);
t('duplicate drops source ID', !array_key_exists('id',$copy));
t('duplicate is inactive', $copy['is_active']===0);
t('duplicate gets requested copy name', $copy['name']==='طرح ویژه — کپی');
t('duplicate appends after current global order', $copy['display_order']===80);
t('duplicate gets fresh creation time', $copy['created_at']==='2026-09-09 00:00:00');
foreach(['type','settings','display_fields','display_fields_context','required_documents'] as $k){
    t('duplicate preserves '.$k, $copy[$k]===$source[$k]);
}
$legacy=$source; unset($legacy['display_order']);
$legacyCopy=duplicate_row($legacy,'x','y',70);
t('duplicate is safe when display_order column is absent', !array_key_exists('display_order',$legacyCopy));
echo "RESULT: {$passed} passed, {$failed} failed\n";
exit($failed?1:0);
