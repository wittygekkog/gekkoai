<?php

namespace Tests;

use App\Models\Admin;
use App\Models\Tenant;
use App\Models\TenantConnector;
use App\Models\TenantOperation;
use App\Models\TenantUser;
use App\Services\Connectors\OperationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class PlatformTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        clearSettingsCache();
        config([
            'gekko.analyzer' => 'deterministic',
            'gekko.allow_live_connectors' => false,
        ]);
    }

    protected function makeAdmin(array $attributes = []): Admin
    {
        $admin = new Admin();
        $admin->name = $attributes['name'] ?? 'Test Admin';
        $admin->username = $attributes['username'] ?? 'operator';
        $admin->email = $attributes['email'] ?? $admin->username.'@example.test';
        $admin->password = $attributes['password'] ?? 'secret1';
        $admin->access_scope = $attributes['access_scope'] ?? 'platform';
        $admin->must_change_password = $attributes['must_change_password'] ?? false;
        $admin->admin_type = $attributes['admin_type'] ?? 0;
        $admin->status = $attributes['status'] ?? 1;
        $admin->save();

        return $admin;
    }

    protected function makeTenant(string $slug, bool $withOperations = false): Tenant
    {
        $tenant = new Tenant();
        $tenant->name = $slug;
        $tenant->slug = $slug;
        $tenant->timezone = 'Asia/Manila';
        $tenant->locale = 'en';
        $tenant->status = 'active';
        $tenant->save();

        $connector = new TenantConnector();
        $connector->tenant_id = $tenant->id;
        $connector->base_url = 'simulation://'.$slug;
        $connector->mode = 'simulation';
        $connector->status = 'simulation';
        $connector->key_id = 'sim-'.$slug;
        $connector->save();

        if ($withOperations) {
            foreach (OperationCatalog::definitions() as $definition) {
                $operation = new TenantOperation();
                $operation->tenant_id = $tenant->id;
                $operation->operation_name = $definition->name;
                $operation->version = $definition->version;
                $operation->description = $definition->description;
                $operation->input_schema = $definition->inputSchema;
                $operation->output_schema = $definition->outputSchema;
                $operation->risk_level = $definition->risk;
                $operation->enabled = true;
                $operation->approval_required = $definition->approvalRequired;
                $operation->read_only = $definition->readOnly;
                $operation->required_fields = $definition->requiredFields;
                $operation->save();
            }
        }

        return $tenant;
    }

    protected function assign(Admin $admin, Tenant $tenant, string $role): void
    {
        $admin->access_scope = 'assigned';
        $admin->admin_type = 1;
        $admin->save();

        $membership = new TenantUser();
        $membership->tenant_id = $tenant->id;
        $membership->user_id = $admin->id;
        $membership->role = $role;
        $membership->status = 'active';
        $membership->save();
    }
}
