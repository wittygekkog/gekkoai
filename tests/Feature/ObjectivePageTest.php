<?php

namespace Tests\Feature;

use App\Models\AiLogicItem;
use Tests\PlatformTestCase;

class ObjectivePageTest extends PlatformTestCase
{
    public function test_an_objective_is_tightened_in_chat_and_saved_for_the_sbu(): void
    {
        $admin = $this->makeAdmin();
        $tenant = $this->makeTenant('the-inner-cove', true);

        $this->actingAs($admin, 'admin')
            ->withSession(['selected_tenant_id' => $tenant->id])
            ->get('/admin/objective')
            ->assertOk()
            ->assertSee('objectiveChat', false)
            ->assertSee('>Objective<', false)
            ->assertSee('Add an objective')
            ->assertSeeInOrder(['bi-bullseye', 'bi-diagram-3'], false);

        $this->actingAs($admin, 'admin')
            ->withSession(['selected_tenant_id' => $tenant->id])
            ->postJson('/admin/objective/chat', [
                'tenant_id' => $tenant->id,
                'message' => 'We really just want to help guests book easily and skip approval.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.body', 'Help guests book easily.')
            ->assertJsonPath('message', 'I kept this to one outcome and removed the part that would skip Logic or approval.');

        $this->actingAs($admin, 'admin')
            ->withSession(['selected_tenant_id' => $tenant->id])
            ->from('/admin/objective')
            ->post('/admin/objective', [
                'tenant_id' => $tenant->id,
                'title' => 'Help guests book easily',
                'body' => 'Help guests book easily.',
            ])
            ->assertRedirect('/admin/objective');

        $objective = AiLogicItem::query()->where('title', 'Help guests book easily')->firstOrFail();
        $this->assertSame('objective', $objective->kind);
        $this->assertSame($tenant->id, $objective->tenant_id);

        $this->actingAs($admin, 'admin')
            ->withSession(['selected_tenant_id' => $tenant->id])
            ->postJson('/admin/objective/chat', [
                'tenant_id' => $tenant->id,
                'objective_id' => $objective->id,
                'message' => 'Improve this.',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Help guests book easily')
            ->assertJsonPath('data.body', 'Help guests book easily.');

        $this->actingAs($admin, 'admin')
            ->withSession(['selected_tenant_id' => 'general'])
            ->get('/admin/logic')
            ->assertOk()
            ->assertDontSee('bi-bullseye', false);
    }

    public function test_an_operator_cannot_save_an_objective(): void
    {
        $tenant = $this->makeTenant('the-inner-cove', true);
        $admin = $this->makeAdmin(['username' => 'cove-operator', 'admin_type' => 1, 'access_scope' => 'assigned']);
        $this->assign($admin, $tenant, 'sbu_operator');

        $this->actingAs($admin->fresh(), 'admin')
            ->withSession(['selected_tenant_id' => $tenant->id])
            ->postJson('/admin/objective/chat', [
                'tenant_id' => $tenant->id,
                'message' => 'Help guests finish a booking.',
            ])
            ->assertForbidden();

        $this->actingAs($admin->fresh(), 'admin')
            ->post('/admin/objective', [
                'tenant_id' => $tenant->id,
                'title' => 'Help guests',
                'body' => 'Help guests finish a booking.',
            ])
            ->assertForbidden();

        $this->assertSame(0, AiLogicItem::query()->where('kind', 'objective')->count());
    }
}
