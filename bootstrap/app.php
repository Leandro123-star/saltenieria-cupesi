<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Railway termina SSL en su proxy y reenvía por HTTP: se confía en sus
        // headers X-Forwarded-* para que Laravel detecte el esquema https real.
        $middleware->trustProxies(at: '*');

        $middleware->alias(['sucursal.context' => \App\Http\Middleware\EnsureSucursalContext::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Laravel convierte TokenMismatchException en HttpException(419) antes
        // de los callbacks de render, por eso se filtra por el código de estado.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Tu sesión expiró. Vuelve a iniciar sesión.',
                ], 419);
            }

            return redirect()->route('login', ['expirada' => 1]);
        });
    })->create();
