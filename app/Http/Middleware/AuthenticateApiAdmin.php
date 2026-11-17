<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\AdminApiToken;
use App\Services\MobileTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accepts a bearer token issued for the Android app and binds the admin guard
 * for the rest of the request. Session cookies are not required.
 */
class AuthenticateApiAdmin
{
    public function __construct(private readonly MobileTokenService $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $plain = $this->bearer($request);
        $token = $plain === null ? null : $this->tokens->findByAccessToken($plain);
        $admin = $token?->admin;
        if (! $token instanceof AdminApiToken || ! $admin instanceof Admin || ! $admin->isActive()) {
            return $this->denied(401, 'Your session has expired. Sign in again.', 'unauthenticated');
        }

        Auth::guard('admin')->setUser($admin);
        $request->setUserResolver(static fn () => $admin);
        $this->tokens->touch($token);

        if ($admin->must_change_password && ! $request->is('api/v1/auth/logout')) {
            return $this->denied(
                403,
                'Change your password on the administration site before using the mobile app.',
                'password_change_required',
            );
        }

        return $next($request);
    }

    private function bearer(Request $request): ?string
    {
        $header = (string) $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ')) {
            return null;
        }
        $token = trim(substr($header, 7));

        return $token === '' ? null : $token;
    }

    private function denied(int $status, string $message, string $code): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => ['code' => $code],
        ], $status);
    }
}
