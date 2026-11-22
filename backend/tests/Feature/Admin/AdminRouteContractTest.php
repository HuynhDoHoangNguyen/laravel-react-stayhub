<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\UserManagement\AccountStatusController;
use App\Http\Controllers\UserManagement\AdminCustomerController;
use App\Http\Controllers\UserManagement\AdminHostController;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminRouteContractTest extends TestCase
{
    public static function moduleOrders(): array
    {
        return [['users.php', 'admin_users.php'], ['admin_users.php', 'users.php']];
    }

    #[DataProvider('moduleOrders')]
    public function test_user_modules_select_the_same_unique_controller_in_either_order(string $first, string $second): void
    {
        $original = Route::getFacadeRoot();
        $router = new Router(new Dispatcher($this->app), $this->app);
        try {
            Route::swap($router);
            $router->group(['prefix' => 'api'], function () use ($first, $second): void {
                require base_path('routes/api/'.$first);
                require base_path('routes/api/'.$second);
            });
            $expected = [
                ['GET', '/api/admin/users', AdminCustomerController::class.'@index'],
                ['GET', '/api/admin/users/2', AdminCustomerController::class.'@show'],
                ['GET', '/api/admin/hosts', AdminHostController::class.'@index'],
                ['GET', '/api/admin/hosts/2', AdminHostController::class.'@show'],
                ['GET', '/api/admin/user-management', AdminUserController::class.'@index'],
                ['POST', '/api/admin/users/2/approve-host', AdminUserController::class.'@approveHost'],
                ['PATCH', '/api/admin/users/2/status', AccountStatusController::class.'@update'],
            ];
            foreach ($expected as [$method, $path, $action]) {
                $matched = $router->getRoutes()->match(Request::create($path, $method));
                $this->assertSame($action, $matched->getActionName());
                foreach (['auth:sanctum', 'account.active', 'role:ADMIN'] as $middleware) {
                    $this->assertContains($middleware, $matched->gatherMiddleware());
                }
            }
            $patches = array_filter($router->getRoutes()->getRoutes(),
                static fn ($route) => in_array('PATCH', $route->methods(), true));
            $this->assertCount(1, $patches);
        } finally {
            Route::swap($original);
        }
    }

    public function test_full_application_keeps_existing_and_incoming_modules_registered(): void
    {
        $router = app('router');
        foreach ([
            ['GET', '/api/admin/users', AdminCustomerController::class.'@index'],
            ['GET', '/api/admin/user-management', AdminUserController::class.'@index'],
            ['GET', '/api/admin/hosts', AdminHostController::class.'@index'],
            ['PATCH', '/api/admin/users/2/status', AccountStatusController::class.'@update'],
        ] as [$method, $path, $action]) {
            $this->assertSame($action, $router->getRoutes()->match(Request::create($path, $method))->getActionName());
        }
        foreach ([['GET', '/api/admin/bookings'], ['GET', '/api/admin/commissions'],
            ['GET', '/api/public/partner-agreements/current'], ['GET', '/api/host/partner-agreements/current'],
            ['POST', '/api/customer/chat/messages'], ['POST', '/api/public/chat/messages']] as [$method, $path]) {
            $this->assertNotNull($router->getRoutes()->match(Request::create($path, $method)));
        }
    }
}
