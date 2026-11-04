<?php
declare(strict_types=1);
namespace Babanoell\Documents\Domain;
final class CooperationDocumentPolicy
{
    public static function fromOwner(array$request):array
    {
        if(!array_key_exists('document_policy',$request)){
            if(array_key_exists('_form',$request['data']??[]))throw new ApiException(503,'partners_response_invalid','سیاست مدارک فرم قابل تأیید نیست.');
            return array_map(static fn(string$code):array=>['code'=>$code,'visible'=>true,'required'=>false],['cooperation_identity','cooperation_license','cooperation_contract']);
        }
        $rows=$request['document_policy'];$seen=[];
        if(!is_array($rows)||!array_is_list($rows)||count($rows)<1||count($rows)>32)throw new ApiException(503,'partners_response_invalid','سیاست مدارک فرم قابل تأیید نیست.');
        foreach($rows as$row){$code=$row['code']??null;if(!is_string($code)||!str_starts_with($code,'cooperation_')||!isset(DocumentCatalog::all()[$code])||isset($seen[$code])||!is_bool($row['visible']??null)||!is_bool($row['required']??null)||(!$row['visible']&&$row['required']))throw new ApiException(503,'partners_response_invalid','سیاست مدارک فرم قابل تأیید نیست.');$seen[$code]=true;}
        return$rows;
    }
}
