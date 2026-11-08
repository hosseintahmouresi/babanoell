<?php
declare(strict_types=1);

namespace Babanoell\Auth\App;

use Babanoell\Auth\Http\Controllers\AuthController;
use Babanoell\Auth\Http\JsonResponse;
use Babanoell\Auth\Http\Request;
use Babanoell\Auth\Http\Router;
use Babanoell\Auth\Database\Connection;
use Babanoell\Auth\Platform\ServiceBoundary;
use Throwable;

final class Routes
{
    public static function build(Container $container): Router
    {
        $router = new Router();
        $config = $container->config();

        $router->get('/health', static fn (Request $request): JsonResponse => new JsonResponse(200, [
            'service' => $config->serviceName(),
            'status' => 'ok',
            'environment' => $config->environment(),
            'timestamp' => gmdate('c'),
        ]));

        $router->get('/healthz', static function (Request $request) use ($config): JsonResponse {
            $serviceRoot = dirname(__DIR__, 2);
            $localConfig = $serviceRoot . '/config/local.env.php';

            // Before installation healthz stays a lightweight runtime check.
            // After installation it becomes a readiness check and performs a
            // real database round-trip, so a broken cPanel DB configuration is
            // visible immediately instead of being reported as healthy.
            if (is_file($localConfig)) {
                try {
                    $dbConfig = require $serviceRoot . '/config/database.php';
                    $pdo = Connection::fromConfig($dbConfig);
                    $pdo->query('SELECT 1')->fetchColumn();
                } catch (Throwable) {
                    return new JsonResponse(503, [
                        'service' => $config->serviceName(),
                        'status' => 'error',
                        'database' => 'unavailable',
                        'environment' => $config->environment(),
                        'timestamp' => gmdate('c'),
                    ]);
                }
            }

            return new JsonResponse(200, [
                'service' => $config->serviceName(),
                'status' => 'ok',
                'database' => is_file($localConfig) ? 'ok' : 'not_configured',
                'environment' => $config->environment(),
                'timestamp' => gmdate('c'),
            ]);
        });

        $router->get('/', static fn (Request $request): JsonResponse => new JsonResponse(200, [
            'service' => $config->serviceName(),
            'message' => 'سرویس احراز هویت پلتفرم بابانوئل فعال است.',
            'api_version' => 'v1',
        ]));

        $router->get('/api/v1/meta/platform-contract', static fn (Request $request): JsonResponse => new JsonResponse(200, [
            'data' => [
                'contract' => ServiceBoundary::describe(),
                'service_auth' => [
                    'mode' => $config->serviceAuthMode(),
                    'configured' => $config->serviceAuthSecret() !== '',
                ],
            ],
        ]));

        $authController = $container->authController();
        $meController = $container->meController();

        foreach(['discover','register','resend','verify'] as $action){
            $router->post('/api/v1/auth/smart/'.$action,static function(Request $request)use($container,$config,$action):JsonResponse{
                $caller=\Babanoell\Auth\Platform\RequestContext::callerService();
                if(strlen($config->serviceAuthSecret())<32||$caller!=='panel'||!\Babanoell\Auth\Platform\PayloadAuth::verify($request->header('X-Noel-Payload-Signature'),'panel',(string)$request->header('X-Noel-Timestamp'),(string)$request->header('X-Request-Id'),$request->method(),$request->path(),$request->rawBody(),$config->serviceAuthSecret()))return JsonResponse::error(403,'service_access_denied','دسترسی سرویس مجاز نیست.');
                try{$body=$request->json();$service=$container->smartAuth();$context=new \Babanoell\Auth\UseCases\Login\LoginSecurityContext(is_string($body['client_ip']??null)?$body['client_ip']:null,$request->header('User-Agent'),$request->header('X-Request-Id'));$result=$action==='verify'?$service->verify($body,$context):$service->$action($body);return JsonResponse::ok($result);}
                catch(\Babanoell\Auth\SmartAuth\AuthFailure $e){return new JsonResponse($e->status,['error'=>['code'=>$e->publicCode,'message'=>$e->getMessage(),'retry_after'=>$e->retryAfter]],$e->retryAfter>0?['Retry-After'=>(string)$e->retryAfter]:[]);}
                catch(\InvalidArgumentException){return JsonResponse::error(422,'validation_failed','اطلاعات فرم معتبر نیست.');}
                catch(Throwable){return JsonResponse::error(503,'auth_unavailable','سرویس ورود موقتاً در دسترس نیست.');}
            });
        }
        foreach(['GET','POST'] as $method)$router->add($method,'/api/v1/admin/auth-settings',static function(Request $request)use($container):JsonResponse{
            try{$auth=$container->adminAuthorization()->requireSuperAdmin($request);if(!$auth['ok'])return JsonResponse::error($auth['status'],$auth['code'],$auth['message']);$service=$container->smartAuth();return JsonResponse::ok(['settings'=>$request->method()==='POST'?$service->saveSettings($request->json(),$auth['user_id']):$service->settings()]);}
            catch(\Babanoell\Auth\SmartAuth\AuthFailure $e){return JsonResponse::error($e->status,$e->publicCode,$e->getMessage());}catch(Throwable){return JsonResponse::error(503,'auth_unavailable','تنظیمات ورود دریافت نشد.');}
        });

        $router->post('/api/v1/auth/login', [$authController, 'login']);
        $router->post('/api/v1/auth/logout', [$authController, 'logout']);
        $router->post('/api/v1/auth/refresh', [$authController, 'refresh']);
        $router->get('/api/v1/auth/me', [$meController, 'show']);
        $router->post('/api/v1/services/notification-contact', static function(Request $request) use ($container,$config): JsonResponse {
            $caller=\Babanoell\Auth\Platform\RequestContext::callerService();
            if(!in_array($caller,['loans','partners'],true)||!\Babanoell\Auth\Platform\PayloadAuth::verify(
                $request->header('X-Noel-Payload-Signature'),$caller,(string)$request->header('X-Noel-Timestamp'),
                (string)$request->header('X-Request-Id'),$request->method(),$request->path(),$request->rawBody(),$config->serviceAuthSecret()))
                return \Babanoell\Auth\Http\ResponseMapper::error(403,'service_access_denied','دسترسی سرویس مجاز نیست.');
            return $container->managementController()->notificationContact($request);
        });
        $router->post('/api/v1/services/plan-policy',static function(Request $request)use($container,$config):JsonResponse{
            $caller=\Babanoell\Auth\Platform\RequestContext::callerService();
            if($caller!=='partners'||!\Babanoell\Auth\Platform\PayloadAuth::verify($request->header('X-Noel-Payload-Signature'),$caller,(string)$request->header('X-Noel-Timestamp'),(string)$request->header('X-Request-Id'),$request->method(),$request->path(),$request->rawBody(),$config->serviceAuthSecret()))return \Babanoell\Auth\Http\ResponseMapper::error(403,'service_access_denied','دسترسی سرویس مجاز نیست.');
            return $container->managementController()->planPolicy($request);
        });
        $router->get('/api/v1/roles', static fn (Request $request): JsonResponse => $container->managementController()->roles($request));
        $router->get('/api/v1/admin/users', static fn (Request $request): JsonResponse => $container->managementController()->users($request));
        $router->post('/api/v1/admin/users', static fn (Request $request): JsonResponse => $container->managementController()->createUser($request));
        $router->post('/api/v1/partners/activate-acceptor', static fn (Request $request): JsonResponse => $container->managementController()->activatePartner($request));
        $router->post('/api/v1/admin/user-roles', static function (Request $request) use ($container): JsonResponse {
            $body = $request->json();
            $userId = trim((string) ($body['user_id'] ?? ''));
            if ($userId === '') return \Babanoell\Auth\Http\ResponseMapper::error(422, 'validation_failed', 'شناسه کاربر الزامی است.');
            return $container->managementController()->replaceRoles($request, $userId);
        });
        $router->post('/api/v1/admin/user-status', static function (Request $request) use ($container): JsonResponse {
            $body = $request->json();
            $userId = trim((string) ($body['user_id'] ?? ''));
            if ($userId === '') return \Babanoell\Auth\Http\ResponseMapper::error(422, 'validation_failed', 'شناسه کاربر الزامی است.');
            return $container->managementController()->status($request, $userId);
        });
        $router->get('/api/v1/permissions', static fn (Request $request): JsonResponse => $container->managementController()->permissions($request));
        $router->get('/api/v1/admin/organizations', static fn (Request $request): JsonResponse => $container->managementController()->organizations($request));
        $router->post('/api/v1/admin/organizations', static fn (Request $request): JsonResponse => $container->managementController()->createOrganization($request));
        $router->get('/api/v1/admin/branches', static function (Request $request) use ($container): JsonResponse { $q=$request->queryParams(); return $container->managementController()->branches($request,trim((string)($q['organization_id']??''))); });
        $router->post('/api/v1/admin/branches', static function (Request $request) use ($container): JsonResponse { $b=$request->json();$org=trim((string)($b['organization_id']??''));if($org==='') return \Babanoell\Auth\Http\ResponseMapper::error(422,'validation_failed','شناسه سازمان الزامی است.');return $container->managementController()->createBranch($request,$org); });
        $router->get('/api/v1/admin/user-access', static function (Request $request) use ($container): JsonResponse { $q=$request->queryParams(); $uid=trim((string)($q['user_id']??'')); if($uid==='') return \Babanoell\Auth\Http\ResponseMapper::error(422,'validation_failed','شناسه کاربر الزامی است.'); return $container->managementController()->access($request,$uid); });
        $router->post('/api/v1/admin/user-memberships', static function (Request $request) use ($container): JsonResponse { $b=$request->json();$uid=trim((string)($b['user_id']??''));if($uid==='') return \Babanoell\Auth\Http\ResponseMapper::error(422,'validation_failed','شناسه کاربر الزامی است.');return $container->managementController()->memberships($request,$uid); });
        $router->post('/api/v1/admin/user-scopes', static function (Request $request) use ($container): JsonResponse { $b=$request->json();$uid=trim((string)($b['user_id']??''));if($uid==='') return \Babanoell\Auth\Http\ResponseMapper::error(422,'validation_failed','شناسه کاربر الزامی است.');return $container->managementController()->grants($request,$uid); });

        return $router;
    }
}
