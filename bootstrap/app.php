<?php

use App\Http\Middleware\Authenticate;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Modules\Role\Http\Middleware\RoleMiddleware;
use Modules\User\Http\Middleware\AdminMiddleware;
use Modules\User\Http\Middleware\CanCreateUserRole;
use Modules\User\Http\Middleware\CashierMiddleware;
use Modules\User\Http\Middleware\ManagerMiddleware;
use Modules\User\Http\Middleware\SuperAdminMiddleware;
use Modules\User\Http\Middleware\WarehouseStaffMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth' => Authenticate::class,
            'can_create_user_role' => CanCreateUserRole::class,
            'role' => RoleMiddleware::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 401);
            }
        });
    })->create();
