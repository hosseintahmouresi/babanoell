<?php
declare(strict_types=1);

namespace Babanoell\Auth\Http\Controllers;

use Babanoell\Auth\Administration\AdminAuthorization;
use Babanoell\Auth\Administration\UserDirectory;
use Babanoell\Auth\Http\JsonResponse;
use Babanoell\Auth\Http\Request;
use Babanoell\Auth\Http\ResponseMapper;
use RuntimeException;
use Throwable;

final class ManagementController
{
    public function __construct(private readonly \Closure $authFactory,private readonly \Closure $directoryFactory){}

    public function roles(Request$request):JsonResponse{$admin=$this->admin($request);if($admin instanceof JsonResponse)return$admin;return new JsonResponse(200,['data'=>['items'=>$this->directory()->roles()]]);}
    public function users(Request$request):JsonResponse{$admin=$this->admin($request);if($admin instanceof JsonResponse)return$admin;$q=$request->queryParams();return new JsonResponse(200,['data'=>$this->directory()->listUsers((int)($q['page']??1),(int)($q['per_page']??25),trim((string)($q['q']??''))) ]);}
    public function createUser(Request$request):JsonResponse{$admin=$this->admin($request);if($admin instanceof JsonResponse)return$admin;try{$u=$this->directory()->create($request->json(),(string)$admin['user_id']);return new JsonResponse(201,['data'=>['user'=>$u]]);}catch(RuntimeException$e){return ResponseMapper::error(422,'validation_failed',$e->getMessage());}}
    public function replaceRoles(Request$request,string$userId):JsonResponse{$admin=$this->admin($request);if($admin instanceof JsonResponse)return$admin;try{$body=$request->json();$roles=is_array($body['roles']??null)?$body['roles']:[];$u=$this->directory()->replaceRoles($userId,$roles,(string)$admin['user_id']);return new JsonResponse(200,['data'=>['user'=>$u]]);}catch(RuntimeException$e){return ResponseMapper::error(422,'validation_failed',$e->getMessage());}}
    public function status(Request$request,string$userId):JsonResponse{$admin=$this->admin($request);if($admin instanceof JsonResponse)return$admin;try{$body=$request->json();$u=$this->directory()->setStatus($userId,strtolower(trim((string)($body['status']??''))),(string)$admin['user_id']);return new JsonResponse(200,['data'=>['user'=>$u]]);}catch(RuntimeException$e){return ResponseMapper::error(422,'validation_failed',$e->getMessage());}}

    private function directory(): UserDirectory
    {
        $factory = $this->directoryFactory;
        return $factory();
    }

    /** @return array<string,mixed>|JsonResponse */
    private function admin(Request $request): array|JsonResponse
    {
        $authorization = $request->header('Authorization');
        if ($authorization === null || preg_match('/^Bearer\s+\S+$/i', trim($authorization)) !== 1) {
            return ResponseMapper::error(401, 'auth_required', 'احراز هویت الزامی است.');
        }

        try {
            $factory = $this->authFactory;
            $r = $factory()->requireSuperAdmin($request);
        } catch (Throwable) {
            return ResponseMapper::error(503, 'auth_unavailable', 'مدیریت کاربران موقتاً در دسترس نیست.');
        }
        if (!$r['ok']) {
            return ResponseMapper::error((int) $r['status'], (string) $r['code'], (string) $r['message']);
        }
        return $r;
    }
}
