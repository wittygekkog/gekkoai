<?php

use App\Http\Middleware\EnsureAdminIsActive;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureSuperAdmin;
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
            'admin.active' => EnsureAdminIsActive::class,
            'password.changed' => EnsurePasswordChanged::class,
            'superadmin' => EnsureSuperAdmin::class,
        ]);
        $middleware->redirectGuestsTo('/admin/login');
        $middleware->validateCsrfTokens(except: [
            'github/deploy',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (QueryException $exception, Request $request) {
            $message = ($exception->errorInfo[0] ?? '') === '23000'
                ? 'That record conflicts with one that already exists. Change the duplicate value and try again.'
                : 'The database could not complete that request. Please review the values and try again.';

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'message' => $message, 'data' => []], 422);
            }

            return redirect()->back()->withInput()->with('error', $message);
        });
    })->create();
