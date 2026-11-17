<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Support\Collection;

/**
 * Tenant and permission checks. Superadmins are unrestricted.
 * Platform-scoped admins can use every business module, but not settings.
 * Assigned admins only see the SBUs linked to their account.
 */
final class AccessService
{
    /** @var array<string, list<string>> */
    private const ROLES = [
        'sbu_administrator' => ['*'],
        'sbu_operator' => ['tenants.view', 'ai.chat.use', 'ai.operations.execute', 'ai.operations.retry', 'ai.audit.view'],
        'sbu_viewer' => ['tenants.view', 'ai.audit.view'],
        'human_reviewer' => ['tenants.view', 'ai.chat.use', 'ai.operations.approve', 'ai.operations.reject', 'ai.memory.review', 'ai.audit.view', 'ai.escalations.resolve'],
    ];

    public function allows(Admin $admin, Tenant $tenant, string $permission): bool
    {
        if (! $admin->isActive() || $tenant->status !== 'active') {
            return false;
        }
        if ($permission === 'settings.manage') {
            return $admin->isSuperAdmin();
        }
        if ($admin->isSuperAdmin() || $admin->access_scope === 'platform') {
            return true;
        }

        $membership = TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $admin->id)
            ->where('status', 'active')
            ->first();
        if (! $membership instanceof TenantUser) {
            return false;
        }

        $permissions = self::ROLES[$membership->role] ?? [];

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public function assert(Admin $admin, Tenant $tenant, string $permission): void
    {
        if (! $this->allows($admin, $tenant, $permission)) {
            abort(403);
        }
    }

    /**
     * Permission names the mobile Access tab may display.
     * Android must not treat this list as the security boundary.
     *
     * @return list<string>
     */
    public function permissionNames(Admin $admin, ?Tenant $tenant): array
    {
        if ($admin->isSuperAdmin()) {
            return array_merge($this->platformPermissions(), ['settings.manage']);
        }
        if ($admin->access_scope === 'platform') {
            return $this->platformPermissions();
        }
        if (! $tenant instanceof Tenant) {
            return [];
        }
        $membership = TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $admin->id)
            ->where('status', 'active')
            ->first();
        if (! $membership instanceof TenantUser) {
            return [];
        }
        $permissions = self::ROLES[$membership->role] ?? [];

        return array_values($permissions);
    }

    public function membershipRole(Admin $admin, Tenant $tenant): ?string
    {
        if ($admin->isSuperAdmin() || $admin->access_scope === 'platform') {
            return $admin->isSuperAdmin() ? 'superadmin' : 'platform_admin';
        }
        $membership = TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $admin->id)
            ->where('status', 'active')
            ->first();

        return $membership instanceof TenantUser ? $membership->role : null;
    }

    /**
     * @return list<string>
     */
    private function platformPermissions(): array
    {
        return [
            'tenants.view',
            'tenants.manage',
            'ai.chat.use',
            'ai.operations.execute',
            'ai.operations.retry',
            'ai.operations.approve',
            'ai.operations.reject',
            'ai.audit.view',
            'ai.memory.review',
            'ai.memory.manage',
            'ai.escalations.resolve',
            'ai.connectors.manage',
        ];
    }

    /**
     * General Administration uses "general" or "all". Anything else is one SBU id.
     */
    public function isSpecificTenant(mixed $selected): bool
    {
        return is_string($selected)
            && $selected !== ''
            && ! in_array($selected, ['all', 'general'], true);
    }

    /**
     * Tenant ids the current sidebar scope is allowed to query.
     *
     * @return Collection<int, string>
     */
    public function scopedTenantIds(Admin $admin, mixed $selected): Collection
    {
        $ids = $this->tenantsFor($admin)->pluck('id');
        if (! $this->isSpecificTenant($selected)) {
            return $ids->values();
        }
        abort_unless($ids->contains($selected), 403);

        return collect([$selected]);
    }

    /**
     * @return Collection<int, Tenant>
     */
    public function tenantsFor(Admin $admin): Collection
    {
        $query = Tenant::query()->orderBy('name');
        if ($admin->isSuperAdmin() || $admin->access_scope === 'platform') {
            return $query->get();
        }

        $ids = TenantUser::query()
            ->where('user_id', $admin->id)
            ->where('status', 'active')
            ->pluck('tenant_id');

        return $query->whereIn('id', $ids)->get();
    }
}
