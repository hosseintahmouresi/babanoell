<?php
declare(strict_types=1);
namespace Babanoell\Panel\Documents;
use Babanoell\Panel\Http\DocumentsClient;
use Babanoell\Panel\Security\Csrf;

/** Session/CSRF protected same-origin gateway; service credentials never reach JavaScript. */
final class ChunkUploadModule
{
    public function __construct(private readonly DocumentsClient $client,private readonly string $bearer){}
    public function handle(string $path,string $method):bool
    {
        if(!preg_match('#^/uploads/chunks(?:/([a-f0-9]{26})(?:/(parts|complete|abort))?)?$#',$path,$m))return false;
        header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private,no-store');header('X-Content-Type-Options: nosniff');
        $id=$m[1]??'';$action=$m[2]??'';
        if($method==='GET'&&$id!==''&&$action==='')$r=$this->client->get('/api/v1/uploads/'.$id,$this->bearer);
        elseif($method==='POST'){
            $content=(string)($_SERVER['CONTENT_TYPE']??'');$b=str_starts_with($content,'application/json')?json_decode((string)file_get_contents('php://input'),true):$_POST;
            if(!is_array($b)||!Csrf::valid((string)($b['_csrf']??''))){http_response_code(403);echo json_encode(['ok'=>false,'error'=>['message'=>'اعتبار فرم منقضی شده است؛ صفحه را تازه‌سازی کنید.']],JSON_UNESCAPED_UNICODE);return true;}
            unset($b['_csrf']);
            if($id==='')$r=$this->client->post('/api/v1/uploads',$this->bearer,$b);
            elseif($action==='parts'){$index=filter_var($b['index']??null,FILTER_VALIDATE_INT);if($index===false||!is_array($_FILES['file']??null)){http_response_code(422);echo json_encode(['ok'=>false,'error'=>['message'=>'قطعهٔ فایل دریافت نشد.']],JSON_UNESCAPED_UNICODE);return true;}$r=$this->client->uploadPart($id,$this->bearer,$_FILES['file'],$index);}
            elseif(in_array($action,['complete','abort'],true))$r=$this->client->post('/api/v1/uploads/'.$id.'/'.$action,$this->bearer,[]);
            else{http_response_code(405);return true;}
        }else{http_response_code(405);return true;}
        http_response_code($r->status>=100?$r->status:503);echo json_encode($r->json??['ok'=>false,'error'=>['message'=>'ارتباط با سرویس مدارک کامل نشد.']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);return true;
    }
}
