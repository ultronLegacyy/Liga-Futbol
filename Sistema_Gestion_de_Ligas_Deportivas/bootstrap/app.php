<?php

use App\Http\Middleware\CheckSessionTimeout;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'session.timeout' => CheckSessionTimeout::class,
        ]);

        // Aplicacion del middleware de control de inactividad de sesion al grupo de rutas web
        $middleware->appendToGroup('web', CheckSessionTimeout::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Control de respuesta 403 Forbidden para excepciones de permisos y roles de Spatie
        $exceptions->render(function (UnauthorizedException $exception, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Acceso denegado',
                    'mensaje' => 'No posee los permisos requeridos para acceder a este recurso administrativo.',
                    'status' => 403,
                ], 403);
            }

            return response()->view('errors.403', [
                'mensaje' => 'No posee los permisos requeridos para acceder a este recurso administrativo.',
                'exception' => $exception,
            ], 403);
        });

        // Control de respuesta 403 Forbidden para excepciones de politicas de autorizacion
        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            $mensaje = $exception->getMessage() ?: 'No posee la autorizacion requerida para realizar esta operacion.';

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Acceso denegado',
                    'mensaje' => $mensaje,
                    'status' => 403,
                ], 403);
            }

            return response()->view('errors.403', [
                'mensaje' => $mensaje,
                'exception' => $exception,
            ], 403);
        });

        // Control de respuesta 403 Forbidden para excepciones http de denegacion de acceso
        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            $mensaje = $exception->getMessage() ?: 'No posee la autorizacion requerida para realizar esta operacion.';

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Acceso denegado',
                    'mensaje' => $mensaje,
                    'status' => 403,
                ], 403);
            }

            return response()->view('errors.403', [
                'mensaje' => $mensaje,
                'exception' => $exception,
            ], 403);
        });
    })->create();
