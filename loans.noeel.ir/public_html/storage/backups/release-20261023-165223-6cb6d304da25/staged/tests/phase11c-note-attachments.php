<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Domain/NoteAttachmentPolicy.php';
use Babanoell\Loans\Domain\NoteAttachmentPolicy as P;
$n=0;$fail=0;
function trial(string $title,callable $fn):void{global $n,$fail;$n++;try{$fn();echo"[PASS] $title\n";}catch(Throwable $e){$fail++;echo"[FAIL] $title: ".$e->getMessage()."\n";}}
function ok(bool $x):void{if(!$x)throw new RuntimeException('assertion failed');}
function rejects(callable $f):void{try{$f();}catch(InvalidArgumentException){return;}throw new RuntimeException('expected rejection');}
$a=str_repeat('a',26);$b=str_repeat('b',26);$c=str_repeat('c',26);
$d=['document_type'=>'internal_note_attachment','visibility'=>'confidential','status'=>'active','created_by_user_id'=>$a,'links'=>[['entity_type'=>'loan_request','entity_id'=>$b,'relation_code'=>'internal_note']]];
trial('empty',fn()=>ok(P::normalize([])===[]));trial('five',fn()=>ok(count(P::normalize([$a,$b,$c,str_repeat('d',26),str_repeat('e',26)]))===5));
trial('six rejected',fn()=>rejects(fn()=>P::normalize([$a,$b,$c,str_repeat('d',26),str_repeat('e',26),str_repeat('f',26)])));
trial('invalid ID rejected',fn()=>rejects(fn()=>P::normalize(['../etc'])));trial('duplicate rejected',fn()=>rejects(fn()=>P::normalize([$a,$a])));
trial('valid link',fn()=>P::assertDocument($d,$b,$a));
foreach(['document_type'=>'supplemental','visibility'=>'normal','status'=>'archived','created_by_user_id'=>$c]as$k=>$v)trial($k.' protected',function()use($d,$k,$v,$b,$a){$r=$d;$r[$k]=$v;rejects(fn()=>P::assertDocument($r,$b,$a));});
trial('wrong case',fn()=>rejects(fn()=>P::assertDocument($d,$c,$a)));
trial('wrong actor',fn()=>rejects(fn()=>P::assertDocument($d,$b,$c)));
trial('no reused / multiple links',function()use($d,$b,$a){$r=$d;$r['links'][]=$r['links'][0];rejects(fn()=>P::assertDocument($r,$b,$a));});
echo"Loans 11C: $n checks, $fail failed\n";exit($fail?1:0);
