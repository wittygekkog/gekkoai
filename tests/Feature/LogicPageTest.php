<?php

namespace Tests\Feature;

use App\Models\AiLogicItem;
use Tests\PlatformTestCase;

class LogicPageTest extends PlatformTestCase
{
    public function test_the_sbu_sidebar_renames_links_and_serves_logic(): void
    {
        $admin = $this->makeAdmin();
        $tenant = $this->makeTenant('the-inner-cove', true);

        $this->actingAs($admin, 'admin')
            ->withSession(['selected_tenant_id' => $tenant->id])
            ->get('/admin/logic')
            ->assertOk()
            ->assertSee('Logic')
            ->assertSee('Rules')
            ->assertSee('Instructions')
            ->assertSee('Logs')
            ->assertSee('Flags')
            ->assertSee('Data')
            ->assertDontSee('Conversations')
            ->assertDontSee('Operation History')
            ->assertDontSee('Human Escalations')
            ->assertDontSee('Knowledge Base');

        $this->actingAs($admin, 'admin')
            ->withSession(['selected_tenant_id' => $tenant->id])
            ->post('/admin/logic', [
                'tenant_id' => $tenant->id,
                'kind' => 'rule',
                'title' => 'Deposit before confirmation',
                'body' => 'A stay is not treated as confirmed until the deposit is recorded.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ai_logic_items', [
            'tenant_id' => $tenant->id,
            'kind' => 'rule',
            'title' => 'Deposit before confirmation',
        ]);
    }

    public function test_an_operator_cannot_save_logic(): void
    {
        $tenant = $this->makeTenant('the-inner-cove', true);
        $admin = $this->makeAdmin(['username' => 'cove-operator', 'admin_type' => 1, 'access_scope' => 'assigned']);
        $this->assign($admin, $tenant, 'sbu_operator');

        $this->actingAs($admin->fresh(), 'admin')
            ->withSession(['selected_tenant_id' => $tenant->id])
            ->post('/admin/logic', [
                'tenant_id' => $tenant->id,
                'kind' => 'instruction',
                'title' => 'Book a guest',
                'body' => 'Book Ana Reyes from December 10 to December 12 for two guests.',
                'intent' => 'booking.create',
            ])
            ->assertForbidden();

        $this->assertSame(0, AiLogicItem::query()->count());
    }
}
