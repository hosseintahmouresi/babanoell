<?php
declare(strict_types=1);

namespace Babanoell\Auth\App;

use Babanoell\Auth\Http\Controllers\AuthController;
use Babanoell\Auth\Http\Controllers\PermissionsController;
use Babanoell\Auth\Http\JsonResponse;
use Babanoell\Auth\Http\Request;
use Babanoell\Auth\Http\Router;
use Babanoell\Auth\Database\Connection;
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

        $authController = $container->authController();
        $meController = $container->meController();
        $permissionsController = new PermissionsController();

        $router->post('/api/v1/auth/login', [$authController, 'login']);
        $router->post('/api/v1/auth/logout', [$authController, 'logout']);
        $router->post('/api/v1/auth/refresh', [$authController, 'refresh']);
        $router->get('/api/v1/auth/me', [$meController, 'show']);
        $router->get('/api/v1/roles', static fn (Request $request): JsonResponse => $container->managementController()->roles($request));
        $router->get('/api/v1/admin/users', static fn (Request $request): JsonResponse => $container->managementController()->users($request));
        $router->post('/api/v1/admin/users', static fn (Request $request): JsonResponse => $container->managementController()->createUser($request));
        $router->post('/api/v1/admin/user-roles', static function (Request $request) use ($container): JsonResponse {
            $body = $request->json();
            $userId = trim((string) ($body['user_id'] ?? ''));
            if ($userId === '') {
                return \Babanoell\Auth\Http\ResponseMapper::error(422, 'validation_failed', 'شناسه کاربر الزامی است.');
            }
            return $container->managementController()->replaceRoles($request, $userId);
        });
        $router->post('/api/v1/admin/user-status', static function (Request $request) use ($container): JsonResponse {
            $body = $request->json();
            $userId = trim((string) ($body['user_id'] ?? ''));
            if ($userId === '') {
                return \Babanoell\Auth\Http\ResponseMapper::error(422, 'validation_failed', 'شناسه کاربر الزامی است.');
            }
            return $container->managementController()->status($request, $userId);
        });
        $router->get('/api/v1/permissions', [$permissionsController, 'index']);

        return $router;
    }
}
