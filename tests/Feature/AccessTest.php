<?php

namespace Tests\Feature;

use App\Models\Admin;
use Tests\PlatformTestCase;

class AccessTest extends PlatformTestCase
{
    public function test_login_requires_a_password_change_before_the_workspace(): void
    {
        $this->makeAdmin([
            'username' => 'manuel',
            'password' => 'secret1',
            'must_change_password' => true,
        ]);

        $this->post('/admin/login', ['username' => 'manuel', 'password' => 'secret1'])
            ->assertRedirect('/admin');
        $this->get('/admin')->assertRedirect('/admin/password');
        $this->post('/admin/password', [
            'password' => 'changed1',
            'password_confirmation' => 'changed1',
        ])->assertRedirect('/admin');
        $this->get('/admin')->assertOk()->assertSee('Operations overview');
        $this->assertFalse(auth('admin')->user()->must_change_password);
    }

    public function test_a_platform_admin_cannot_open_settings(): void
    {
        $admin = $this->makeAdmin(['username' => 'platform', 'admin_type' => 1, 'access_scope' => 'platform']);

        $this->actingAs($admin, 'admin')
            ->get('/admin/settings')
            ->assertForbidden();
        $this->actingAs($admin, 'admin')
            ->post('/admin/settings', [
                'setting_slug' => 'ai-auto-execution-enabled',
                'setting_name' => 'Automatic writes',
                'setting_group' => 'ai',
                'input_type' => 'boolean',
                'setting_value' => '1',
            ])
            ->assertForbidden();
    }

    public function test_an_admin_cannot_promote_an_account_to_superadmin(): void
    {
        $actor = $this->makeAdmin(['username' => 'platform', 'admin_type' => 1, 'access_scope' => 'platform']);
        $target = $this->makeAdmin(['username' => 'staff', 'admin_type' => 1, 'access_scope' => 'platform']);

        $this->actingAs($actor, 'admin')->patch('/admin/access/'.$target->id, [
            'name' => 'Staff',
            'username' => 'staff',
            'email' => $target->email,
            'admin_type' => 0,
            'status' => 1,
            'access_scope' => 'platform',
        ])->assertForbidden();

        $this->assertSame(1, $target->fresh()->admin_type);
    }

    public function test_assigned_access_does_not_cross_tenants(): void
    {
        $cove = $this->makeTenant('the-inner-cove', true);
        $other = $this->makeTenant('ticketbooth');
        $admin = $this->makeAdmin(['username' => 'cove-only', 'admin_type' => 1, 'access_scope' => 'assigned']);
        $this->assign($admin, $cove, 'sbu_operator');

        $this->actingAs($admin->fresh(), 'admin')
            ->get('/admin/tenants/'.$other->id)
            ->assertForbidden();
        $this->actingAs($admin->fresh(), 'admin')
            ->post('/admin/tenant', ['tenant_id' => $other->id])
            ->assertForbidden();
    }

    public function test_the_deploy_webhook_does_not_run_without_a_secret(): void
    {
        $this->post('/github/deploy', ['ref' => 'refs/heads/main'])
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_the_home_page_sends_guests_to_the_login_screen(): void
    {
        $this->get('/')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('Welcome back');
    }
}
