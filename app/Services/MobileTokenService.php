<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminApiToken;
use Illuminate\Support\Str;

/**
 * Issues and rotates bearer tokens for the Android client.
 * Plaintext tokens are never written to the database or to logs.
 */
final class MobileTokenService
{
    public function issue(Admin $admin, ?string $deviceName, bool $remember): AdminApiToken
    {
        $access = $this->secret();
        $refresh = $this->secret();
        $token = new AdminApiToken();
        $token->admin_id = $admin->id;
        $token->device_name = $deviceName !== null ? Str::limit($deviceName, 120, '') : null;
        $token->access_token_hash = $this->hash($access);
        $token->refresh_token_hash = $this->hash($refresh);
        $token->access_expires_at = now()->addHours($remember ? 24 * 14 : 2);
        $token->refresh_expires_at = now()->addDays($remember ? 90 : 30);
        $token->save();
        $token->setAttribute('plain_access_token', $access);
        $token->setAttribute('plain_refresh_token', $refresh);

        return $token;
    }

    public function findByAccessToken(string $plain): ?AdminApiToken
    {
        if ($plain === '' || strlen($plain) < 20) {
            return null;
        }
        $token = AdminApiToken::query()->where('access_token_hash', $this->hash($plain))->first();
        if (! $token instanceof AdminApiToken || $token->isRevoked()) {
            return null;
        }
        if ($token->access_expires_at === null || $token->access_expires_at->isPast()) {
            return null;
        }

        return $token;
    }

    public function rotate(string $refreshPlain, ?string $deviceName): ?AdminApiToken
    {
        if ($refreshPlain === '') {
            return null;
        }
        $current = AdminApiToken::query()->where('refresh_token_hash', $this->hash($refreshPlain))->first();
        if (! $current instanceof AdminApiToken || $current->isRevoked()) {
            return null;
        }
        if ($current->refresh_expires_at === null || $current->refresh_expires_at->isPast()) {
            return null;
        }
        $admin = $current->admin;
        if (! $admin instanceof Admin || ! $admin->isActive()) {
            $current->revoked_at = now();
            $current->save();

            return null;
        }

        $remember = $current->refresh_expires_at->greaterThan(now()->addDays(40));
        $current->revoked_at = now();
        $current->save();

        return $this->issue($admin, $deviceName ?: $current->device_name, $remember);
    }

    public function revokeAccessToken(string $plain): void
    {
        if ($plain === '') {
            return;
        }
        AdminApiToken::query()
            ->where('access_token_hash', $this->hash($plain))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function touch(AdminApiToken $token): void
    {
        if ($token->last_used_at !== null && $token->last_used_at->greaterThan(now()->subMinutes(5))) {
            return;
        }
        $token->last_used_at = now();
        $token->save();
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(AdminApiToken $token, Admin $admin): array
    {
        return [
            'token_type' => 'Bearer',
            'access_token' => (string) $token->getAttribute('plain_access_token'),
            'refresh_token' => (string) $token->getAttribute('plain_refresh_token'),
            'expires_in' => max(0, $token->access_expires_at->getTimestamp() - now()->getTimestamp()),
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'username' => $admin->username,
                'email' => $admin->email,
                'role' => $admin->isSuperAdmin() ? 'superadmin' : 'admin',
                'admin_type' => (int) $admin->admin_type,
                'access_scope' => $admin->access_scope,
                'must_change_password' => (bool) $admin->must_change_password,
            ],
        ];
    }

    private function secret(): string
    {
        return 'gekko_'.bin2hex(random_bytes(32));
    }

    private function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
