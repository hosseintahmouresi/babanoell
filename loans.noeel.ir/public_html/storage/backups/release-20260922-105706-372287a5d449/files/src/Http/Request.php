<?php
declare(strict_types=1);
namespace Babanoell\Loans\Http;
use InvalidArgumentException;
final class Request
{
    public function __construct(private readonly string $method,private readonly string $path,private readonly array $headers=[],private readonly string $rawBody='',private readonly array $query=[]){ }
    public static function fromGlobals(): self
    {
        $uri=$_SERVER['REQUEST_URI']??'/';$path=parse_url($uri,PHP_URL_PATH);$headers=[];
        if(isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])&&is_string($_SERVER['REDIRECT_HTTP_AUTHORIZATION']))$headers['authorization']=$_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        foreach($_SERVER as$k=>$v){if(!is_string($v))continue;if($k==='CONTENT_TYPE'||$k==='CONTENT_LENGTH'){$headers[strtolower(str_replace('_','-',$k))]=$v;continue;}if(str_starts_with($k,'HTTP_'))$headers[strtolower(str_replace('_','-',substr($k,5)))]=$v;}
        return new self((string)($_SERVER['REQUEST_METHOD']??'GET'),is_string($path)&&$path!==''?$path:'/', $headers,file_get_contents('php://input')?:'',$_GET);
    }
    public function method(): string{return strtoupper($this->method);} public function path(): string{$p='/'.ltrim($this->path,'/');return$p==='/'?'/':rtrim($p,'/');}
    public function header(string $name): ?string{$v=$this->headers[strtolower($name)]??null;return is_string($v)?$v:null;}
    public function bearer(): ?string{$h=$this->header('authorization');if($h!==null&&preg_match('/^Bearer\s+(.+)$/i',trim($h),$m))return trim($m[1]);return null;}
    public function json(): array {if(trim($this->rawBody)==='')return[];$j=json_decode($this->rawBody,true);if(!is_array($j))throw new InvalidArgumentException('بدنه درخواست باید JSON معتبر باشد.');return$j;}
    public function query(): array{return$this->query;}
    public function clientIp(): ?string{return \Babanoell\Loans\Platform\ProxyContext::clientIp($_SERVER);}
    public function userAgent(): ?string{$u=$this->header('user-agent');return$u!==null?substr($u,0,512):null;}
}
