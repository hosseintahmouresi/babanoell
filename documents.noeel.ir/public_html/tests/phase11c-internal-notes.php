<?php
declare(strict_types=1);
$src=dirname(__DIR__).'/src';
require "$src/Domain/ApiException.php";require "$src/Security/Principal.php";require "$src/Domain/InternalNotePolicy.php";require "$src/Domain/DocumentCatalog.php";
use Babanoell\Documents\Domain\InternalNotePolicy as P;
use Babanoell\Documents\Domain\DocumentCatalog as Catalog;
use Babanoell\Documents\Security\Principal;
$a=new Principal(str_repeat('a',26),'Manager',['staff'],['loans.requests.internal_notes','documents.view']);
$b=new Principal(str_repeat('b',26),'Applicant',['applicant'],['documents.view']);
$n=0;$fail=0;
function trial(string $title,callable $fn):void{global $n,$fail;$n++;try{$fn();echo"[PASS] $title\n";}catch(Throwable $e){$fail++;echo"[FAIL] $title: ".$e->getMessage()."\n";}}
function ok(bool$x):void{if(!$x)throw new RuntimeException('assertion failed');}
function rejects(callable$f):void{try{$f();}catch(Babanoell\Documents\Domain\ApiException){return;}throw new RuntimeException('expected denial');}
trial('authorized role',fn()=>ok(P::allowed($a)));trial('applicant denied',fn()=>ok(!P::allowed($b)));
trial('allowed note',fn()=>P::assertUpload($a,'loan_request','internal_note_attachment','internal_note'));
trial('applicant upload denied',fn()=>rejects(fn()=>P::assertUpload($b,'loan_request','internal_note_attachment','internal_note')));
trial('wrong entity denied',fn()=>rejects(fn()=>P::assertUpload($a,'merchant','internal_note_attachment','internal_note')));
trial('wrong relation denied',fn()=>rejects(fn()=>P::assertUpload($a,'loan_request','internal_note_attachment','attachment')));
trial('non-note type with internal relation denied',fn()=>rejects(fn()=>P::assertUpload($a,'loan_request','supplemental','internal_note')));
trial('private read denied',fn()=>rejects(fn()=>P::assertRead($b,['document_type'=>'internal_note_attachment'])));
trial('authorized read',fn()=>P::assertRead($a,['document_type'=>'internal_note_attachment']));
trial('normal document unaffected',fn()=>P::assertRead($b,['document_type'=>'supplemental']));
trial('note always confidential',fn()=>ok(Catalog::get('internal_note_attachment')['visibility']==='confidential'));
echo"Documents 11C: $n checks, $fail failed\n";exit($fail?1:0);
