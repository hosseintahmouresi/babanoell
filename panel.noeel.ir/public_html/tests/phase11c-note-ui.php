<?php
declare(strict_types=1);
$s=(string)file_get_contents(dirname(__DIR__).'/src/Loans/LoansModule.php');
$checks=['protected note permission'=>"$"."this->can('loans.requests.internal_notes')",'anti csrf'=>"Csrf::valid",'multipart form'=>'enctype="multipart/form-data"','up to five files'=>'count($names)>5','concurrent note edit'=>'expected_version','note history'=>'internalNoteHistory','private file type'=>'internal_note_attachment','document API client'=>'$this->documents->upload'];
$fail=0;foreach($checks as $k=>$find){$ok=str_contains($s,$find);echo($ok?'[PASS] ':'[FAIL] ').$k."\n";if(!$ok)$fail++;}echo"Panel 11C: ".count($checks)." checks, $fail failed\n";exit($fail?1:0);
