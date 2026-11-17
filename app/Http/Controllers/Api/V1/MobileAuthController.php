<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\MobileTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Username or email plus password, exchanged for a bearer token.
 * Passwords are checked and then discarded. They are never stored by this controller.
 */
class MobileAuthController extends Controller
{
    public function __construct(private readonly MobileTokenService $tokens) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required_without:username', 'nullable', 'string', 'max:190'],
            'username' => ['required_without:email', 'nullable', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:190'],
            'remember' => ['sometimes', 'boolean'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $login = trim((string) ($data['email'] ?? $data['username'] ?? ''));
        $admin = Admin::query()
            ->where('status', 1)
            ->where(function ($query) use ($login): void {
                $query->where('email', $login)->orWhere('username', $login);
            })
            ->first();

        if (! $admin instanceof Admin || ! Hash::check((string) $data['password'], (string) $admin->password)) {
            return $this->fail('Those credentials do not match an active administrator.', 401, 'invalid_credentials');
        }
        if ($admin->must_change_password) {
            return $this->fail(
                'Change your password on the administration site before using the mobile app.',
                403,
                'password_change_required',
            );
        }

        $token = $this->tokens->issue($admin, $data['device_name'] ?? null, (bool) ($data['remember'] ?? false));

        return response()->json([
            'success' => true,
            'message' => 'Signed in.',
            'data' => $this->tokens->payload($token, $admin),
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $data = $request->validate([
            'refresh_token' => ['required', 'string', 'max:200'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);
        $token = $this->tokens->rotate($data['refresh_token'], $data['device_name'] ?? null);
        $admin = $token?->admin;
        if ($token === null || ! $admin instanceof Admin) {
            return $this->fail('Your session has expired. Sign in again.', 401, 'unauthenticated');
        }
        if ($admin->must_change_password) {
            return $this->fail(
                'Change your password on the administration site before using the mobile app.',
                403,
                'password_change_required',
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Session refreshed.',
            'data' => $this->tokens->payload($token, $admin),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $header = (string) $request->header('Authorization', '');
        $plain = str_starts_with($header, 'Bearer ') ? trim(substr($header, 7)) : '';
        $this->tokens->revokeAccessToken($plain);

        return response()->json([
            'success' => true,
            'message' => 'Signed out.',
            'data' => [],
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return response()->json([
            'success' => true,
            'message' => 'Profile loaded.',
            'data' => [
                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'username' => $admin->username,
                    'email' => $admin->email,
                    'role' => $admin->isSuperAdmin() ? 'superadmin' : 'admin',
                    'admin_type' => (int) $admin->admin_type,
                    'access_scope' => $admin->access_scope,
                ],
            ],
        ]);
    }

    private function fail(string $message, int $status, string $code): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => ['code' => $code],
        ], $status);
    }
}
