<?php

declare(strict_types=1);

use App\Controllers\AlertController;
use App\Controllers\AuthController;
use App\Controllers\DeliveryProposalController;
use App\Controllers\CategoryController;
use App\Controllers\ClientController;
use App\Controllers\MovementController;
use App\Controllers\ProductController;
use App\Controllers\SiteController;
use App\Controllers\StockController;
use App\Controllers\UserController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Routes\Router;
use App\Utils\Response;

$router = new Router($method, $path);

$router->get('/health', function (): void {
    Response::success([
        'status' => 'ok',
        'timestamp' => date('c'),
        'environment' => \App\Config\Config::get('app.env'),
    ]);
});

$authController = new AuthController();
$router->post('/auth/login', [$authController, 'login']);

$router->get('/auth/me', function () use ($router): void {
    $middleware = new AuthMiddleware();
    $user = $middleware->authenticate();
    Response::success($user);
});

$auth = new AuthMiddleware();
$role = new RoleMiddleware();

$withAuth = static function (callable $handler, ?array $roles = null, ?int $siteParamPosition = null) use ($auth, $role) {
    return static function (array $params, array $data) use ($handler, $roles, $siteParamPosition, $auth, $role): void {
        $user = $auth->authenticate();
        if ($roles !== null && $roles !== []) {
            $role->hasRole($user, $roles);
        }
        if ($siteParamPosition !== null) {
            $keys = array_keys($params);
            $siteKey = $keys[$siteParamPosition] ?? null;
            $siteId = $siteKey !== null ? (int)($params[$siteKey] ?? null) : null;
            if ($siteId === null) {
                $siteId = isset($data['siteId']) ? (int)$data['siteId'] : null;
            }
            $role->assertCanAccessSite($user, $siteId);
        }
        $handler($params, $data, $user);
    };
};

$clients = new ClientController();
$router->get('/clients',       $withAuth([$clients, 'index'],   ['ADMIN', 'MANAGER']));
$router->get('/clients/{id}',  $withAuth([$clients, 'show'],    ['ADMIN', 'MANAGER']));
$router->post('/clients',      $withAuth([$clients, 'store'],   ['ADMIN']));
$router->put('/clients/{id}',  $withAuth([$clients, 'update'],  ['ADMIN']));
$router->delete('/clients/{id}', $withAuth([$clients, 'destroy'], ['ADMIN']));

$sites = new SiteController();
$router->get('/sites',                  $withAuth([$sites, 'index'],    ['ADMIN', 'MANAGER', 'STOCK_MANAGER']));
$router->get('/sites/{id}',             $withAuth([$sites, 'show'],     ['ADMIN', 'MANAGER', 'STOCK_MANAGER'], 0));
$router->get('/clients/{clientId}/sites', $withAuth([$sites, 'byClient'], ['ADMIN', 'MANAGER']));
$router->post('/sites',                 $withAuth([$sites, 'store'],    ['ADMIN']));
$router->put('/sites/{id}',             $withAuth([$sites, 'update'],   ['ADMIN']));
$router->delete('/sites/{id}',          $withAuth([$sites, 'destroy'],  ['ADMIN']));

$categories = new CategoryController();
$router->get('/categories',        $withAuth([$categories, 'index'],   ['ADMIN', 'MANAGER', 'STOCK_MANAGER']));
$router->get('/categories/{id}',   $withAuth([$categories, 'show'],    ['ADMIN', 'MANAGER', 'STOCK_MANAGER']));
$router->post('/categories',       $withAuth([$categories, 'store'],   ['ADMIN', 'MANAGER']));
$router->put('/categories/{id}',   $withAuth([$categories, 'update'],  ['ADMIN', 'MANAGER']));
$router->delete('/categories/{id}',$withAuth([$categories, 'destroy'], ['ADMIN']));

$products = new ProductController();
$router->get('/products',       $withAuth([$products, 'index'],   ['ADMIN', 'MANAGER', 'STOCK_MANAGER']));
$router->get('/products/{id}',  $withAuth([$products, 'show'],    ['ADMIN', 'MANAGER', 'STOCK_MANAGER']));
$router->post('/products',      $withAuth([$products, 'store'],   ['STOCK_MANAGER']));
$router->put('/products/{id}',  $withAuth([$products, 'update'],  ['STOCK_MANAGER']));
$router->post('/products/{id}', $withAuth([$products, 'update'],  ['STOCK_MANAGER']));
$router->delete('/products/{id}', $withAuth([$products, 'destroy'], ['STOCK_MANAGER']));

$stocks = new StockController();
$router->get('/stocks',                       $withAuth([$stocks, 'index'],         ['ADMIN', 'MANAGER', 'STOCK_MANAGER']));
$router->get('/stocks/site/{siteId}',         $withAuth([$stocks, 'bySite'],        ['ADMIN', 'MANAGER', 'STOCK_MANAGER'], 0));
$router->get('/stocks/{id}',                  $withAuth([$stocks, 'show'],          ['ADMIN', 'MANAGER', 'STOCK_MANAGER']));
$router->post('/stocks',                      $withAuth([$stocks, 'store'],         ['STOCK_MANAGER'], null));
$router->put('/stocks/{id}',                  $withAuth([$stocks, 'update'],        ['ADMIN', 'MANAGER', 'STOCK_MANAGER']));
$router->post('/stocks/{id}/decrement',       $withAuth([$stocks, 'decrementOne'],  ['ADMIN', 'MANAGER', 'STOCK_MANAGER']));
$router->post('/stocks/{id}/increment',       $withAuth([$stocks, 'incrementOne'],  ['ADMIN', 'MANAGER', 'STOCK_MANAGER']));
$router->post('/stocks/{id}/decrement/{quantity}', $withAuth([$stocks, 'decrementBy'], ['ADMIN', 'MANAGER', 'STOCK_MANAGER']));
$router->post('/stocks/{id}/increment/{quantity}', $withAuth([$stocks, 'incrementBy'], ['ADMIN', 'MANAGER']));

$movements = new MovementController();
$router->get('/stock-movements',                 $withAuth([$movements, 'index'],     ['ADMIN', 'MANAGER', 'STOCK_MANAGER']));
$router->get('/stock-movements/site/{siteId}',   $withAuth([$movements, 'bySite'],    ['ADMIN', 'MANAGER', 'STOCK_MANAGER'], 0));
$router->get('/stock-movements/product/{productId}', $withAuth([$movements, 'byProduct'], ['ADMIN', 'MANAGER']));

$alerts = new AlertController();
$router->get('/alerts',                  $withAuth([$alerts, 'index'],      ['ADMIN', 'MANAGER', 'STOCK_MANAGER']));
$router->get('/alerts/unread',           $withAuth([$alerts, 'unread'],     ['ADMIN', 'MANAGER', 'STOCK_MANAGER']));
$router->get('/alerts/site/{siteId}',    $withAuth([$alerts, 'bySite'],     ['ADMIN', 'MANAGER', 'STOCK_MANAGER'], 0));
$router->put('/alerts/{id}/read',        $withAuth([$alerts, 'markRead'],   ['ADMIN', 'MANAGER', 'STOCK_MANAGER']));
$router->put('/alerts/{id}/resolve',     $withAuth([$alerts, 'resolve'],    ['ADMIN', 'MANAGER']));
$router->post('/alerts/{id}/deliver',    $withAuth([$alerts, 'deliver'],    ['ADMIN']));

$proposals = new DeliveryProposalController();
$router->get('/delivery-proposals', $withAuth([$proposals, 'index'], ['ADMIN', 'MANAGER', 'STOCK_MANAGER']));
$router->post('/delivery-proposals/{id}/confirm', $withAuth([$proposals, 'confirm'], ['MANAGER', 'STOCK_MANAGER']));
$router->post('/delivery-proposals/{id}/reject', $withAuth([$proposals, 'reject'], ['MANAGER', 'STOCK_MANAGER']));

$users = new UserController();
$router->get('/users',                  $withAuth([$users, 'index'],      ['ADMIN', 'MANAGER']));
$router->get('/users/{id}',             $withAuth([$users, 'show'],       ['ADMIN', 'MANAGER']));
$router->post('/users',                 $withAuth([$users, 'store'],      ['ADMIN']));
$router->put('/users/{id}',             $withAuth([$users, 'update'],     ['ADMIN']));
$router->delete('/users/{id}',          $withAuth([$users, 'destroy'],    ['ADMIN']));

$router->run();
