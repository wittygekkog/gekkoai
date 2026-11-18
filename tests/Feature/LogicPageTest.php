<?php

namespace Tests\Feature;

use App\Models\AiLogicItem;
use Tests\PlatformTestCase;

class LogicPageTest extends PlatformTestCase
{
    public function test_rules_are_added_from_the_chat_and_listed_as_accordions(): void
    {
        $admin = $this->makeAdmin();
        $tenant = $this->makeTenant('the-inner-cove', true);

        $this->actingAs($admin, 'admin')
            ->withSession(['selected_tenant_id' => $tenant->id])
            ->get('/admin/logic')
            ->assertOk()
            ->assertSee('logicChat', false)
            ->assertSee('Add a rule')
            ->assertDontSee('Instructions');

        $this->actingAs($admin, 'admin')
            ->withSession(['selected_tenant_id' => $tenant->id])
            ->postJson('/admin/logic/chat', [
                'tenant_id' => $tenant->id,
                'message' => 'Guests cannot check in before 14:00.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Guests cannot check in before 14:00')
            ->assertJsonPath('data.body', 'Guests cannot check in before 14:00.');

        $this->actingAs($admin, 'admin')
            ->withSession(['selected_tenant_id' => $tenant->id])
            ->from('/admin/logic')
            ->post('/admin/logic', [
                'tenant_id' => $tenant->id,
                'title' => 'Deposit before confirmation',
                'body' => 'A stay is not treated as confirmed until the deposit is recorded.',
            ])
            ->assertRedirect('/admin/logic');

        $this->actingAs($admin, 'admin')
            ->withSession(['selected_tenant_id' => $tenant->id])
            ->get('/admin/logic')
            ->assertOk()
            ->assertSee('logicRulesAccordion', false)
            ->assertSee('gk-rule-row', false)
            ->assertSee('Deposit before confirmation')
            ->assertSee('Edit');

        $rule = AiLogicItem::query()->where('title', 'Deposit before confirmation')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->withSession(['selected_tenant_id' => $tenant->id])
            ->postJson('/admin/logic/chat', [
                'tenant_id' => $tenant->id,
                'rule_id' => $rule->id,
                'message' => 'Change the statement to A stay stays pending until the deposit is recorded.',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Deposit before confirmation')
            ->assertJsonPath('data.body', 'A stay stays pending until the deposit is recorded.');

        $this->actingAs($admin, 'admin')
            ->from('/admin/logic')
            ->patch('/admin/logic/'.$rule->id, [
                'tenant_id' => $tenant->id,
                'title' => 'Deposit before confirmation',
                'body' => 'A stay stays pending until the deposit is recorded.',
            ])
            ->assertRedirect('/admin/logic');

        $this->assertSame('A stay stays pending until the deposit is recorded.', $rule->fresh()->body);
    }

    public function test_an_operator_cannot_save_logic(): void
    {
        $tenant = $this->makeTenant('the-inner-cove', true);
        $admin = $this->makeAdmin(['username' => 'cove-operator', 'admin_type' => 1, 'access_scope' => 'assigned']);
        $this->assign($admin, $tenant, 'sbu_operator');

        $this->actingAs($admin->fresh(), 'admin')
            ->withSession(['selected_tenant_id' => $tenant->id])
            ->postJson('/admin/logic/chat', [
                'tenant_id' => $tenant->id,
                'message' => 'Book Ana Reyes from December 10 to December 12 for two guests.',
            ])
            ->assertForbidden();

        $this->actingAs($admin->fresh(), 'admin')
            ->withSession(['selected_tenant_id' => $tenant->id])
            ->post('/admin/logic', [
                'tenant_id' => $tenant->id,
                'title' => 'Book a guest',
                'body' => 'Book Ana Reyes from December 10 to December 12 for two guests.',
            ])
            ->assertForbidden();

        $this->assertSame(0, AiLogicItem::query()->count());
    }
}
