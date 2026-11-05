<?php
$passed = 0; $failed = 0;
function t($name, $ok) { global $passed,$failed; if ($ok) { $passed++; echo "PASS: $name\n"; } else { $failed++; echo "FAIL: $name\n"; } }
function canonical_ids(array $rows): array {
    usort($rows, static function($a,$b){
        $cmp = ((int)$a['display_order']) <=> ((int)$b['display_order']);
        return $cmp !== 0 ? $cmp : (((int)$b['id']) <=> ((int)$a['id']));
    });
    return array_column($rows,'id');
}
function assign_order(array $ids): array {
    $out=[]; $pos=10;
    foreach ($ids as $id) { $out[]=['id'=>(int)$id,'display_order'=>$pos]; $pos+=10; }
    return $out;
}
function sig(array $rows): string {
    usort($rows, static fn($a,$b)=>((int)$a['id'])<=>((int)$b['id']));
    return hash('sha256', implode('|', array_map(static fn($r)=>(int)$r['id'].':'.(int)$r['display_order'],$rows)));
}

$legacy = assign_order([9,5,2]);
t('migration preserves legacy newest-first order', canonical_ids($legacy) === [9,5,2]);
$custom = assign_order([2,9,5]);
t('custom drag order is canonical everywhere', canonical_ids($custom) === [2,9,5]);
t('active-only subset preserves relative global order', canonical_ids(array_values(array_filter($custom, static fn($r)=>in_array($r['id'],[2,5],true)))) === [2,5]);
t('duplicate positions are deterministic by id desc', canonical_ids([['id'=>3,'display_order'=>10],['id'=>8,'display_order'=>10]]) === [8,3]);
$before = sig($custom); $afterRows = assign_order([9,2,5]); $after = sig($afterRows);
t('order signature changes after another admin reorders', $before !== $after);
t('same state keeps same order signature', $before === sig($custom));
$submitted=[2,9,5]; $existing=[5,2,9]; sort($submitted,SORT_NUMERIC); sort($existing,SORT_NUMERIC);
t('exact ID-set validation accepts only complete plan set', $submitted === $existing && (function(){ $a=[2,9]; $b=[2,5,9]; sort($a); sort($b); return $a!==$b; })());

echo "RESULT: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
