<?php

use App\Http\Middleware\ActivateTenantFromToken;
use App\Http\Middleware\EnsureRequestId;
use App\Http\Middleware\EnsureTokenAppAbility;
use App\Http\Middleware\PreLoginTenantResolver;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\SetAppTypeFromRoute;
use App\Http\Middleware\SetRequestLocale;
use App\Support\ApiExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Throwable as BaseThrowable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append([
            EnsureRequestId::class,
            SetRequestLocale::class,
        ]);

        $middleware->throttleApi();

        $middleware->alias([
            'permission' => RequirePermission::class,
            'tenant.auth' => ActivateTenantFromToken::class,
            'app.type' => SetAppTypeFromRoute::class,
            'token.ability' => EnsureTokenAppAbility::class,
            'tenant.resolver' => PreLoginTenantResolver::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(
            fn (BaseThrowable $exception, Request $request) => ApiExceptionRenderer::render($exception, $request),
        );
    })->create();
