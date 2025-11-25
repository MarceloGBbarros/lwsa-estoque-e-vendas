<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Exceptions\InsufficientStockException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php', // <-- ADICIONA ESTA LINHA
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (InsufficientStockException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Estoque insuficiente',
                    'details' => $e->getMessage(),
                ], 422);
            }

            // fallback (se alguém bater via navegador sem JSON)
            return response('Estoque insuficiente', 422);
        });
    })
    ->create();
