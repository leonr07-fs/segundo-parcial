<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/webhooks/paypal',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'ok' => false,
                'message' => 'No autenticado.',
                'errors' => [],
            ], 401);
        });

        $exceptions->render(function (QueryException $exception, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            $message = str_contains($exception->getMessage(), 'Unique violation')
                || str_contains($exception->getMessage(), 'Duplicate entry')
                || str_contains($exception->getMessage(), 'constraint')
                    ? 'Los datos ingresados ya existen o generan conflicto. Revise la informacion antes de continuar.'
                    : 'No se pudo completar la operacion. Intente nuevamente o contacte a administracion.';

            return response()->json([
                'ok' => false,
                'message' => $message,
                'errors' => [],
            ], 422);
        });
    })->create();
