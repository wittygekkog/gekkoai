<?php

use App\Http\Middleware\AuthenticateApiAdmin;
use App\Http\Middleware\EnsureAdminIsActive;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->throttleApi();
        $middleware->alias([
            'admin.active' => EnsureAdminIsActive::class,
            'password.changed' => EnsurePasswordChanged::class,
            'superadmin' => EnsureSuperAdmin::class,
            'auth.api' => AuthenticateApiAdmin::class,
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

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            $message = collect($exception->errors())->flatten()->first();

            return response()->json([
                'success' => false,
                'message' => is_string($message) && $message !== '' ? $message : 'Check the details and try again.',
                'data' => ['errors' => $exception->errors(), 'code' => 'validation'],
            ], $exception->status);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Your session has expired. Sign in again.',
                'data' => ['code' => 'unauthenticated'],
            ], 401);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            $status = $exception->getStatusCode();
            $message = trim($exception->getMessage());
            if ($message === '' || in_array($message, ['Forbidden', 'This action is unauthorized.', 'Not Found', 'Unauthenticated.'], true)) {
                $message = match ($status) {
                    401 => 'Your session has expired. Sign in again.',
                    403 => 'You do not have access to this business unit.',
                    404 => 'That record was not found.',
                    409 => 'This action is no longer available.',
                    422 => 'Check the details and try again.',
                    429 => 'Too many attempts. Wait a moment and try again.',
                    default => 'The request could not be completed.',
                };
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => ['code' => 'http_'.$status],
            ], $status);
        });

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
