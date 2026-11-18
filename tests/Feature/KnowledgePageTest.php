<?php

namespace Tests\Feature;

use App\Models\AiKnowledgeDocument;
use App\Services\KnowledgeService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\PlatformTestCase;

class KnowledgePageTest extends PlatformTestCase
{
    public function test_documents_belong_to_the_selected_sbu_and_can_be_changed(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();
        $tenant = $this->makeTenant('the-inner-cove');
        $other = $this->makeTenant('corondeli');

        $foreign = new AiKnowledgeDocument();
        $foreign->tenant_id = $other->id;
        $foreign->title = 'Corondeli notes';
        $foreign->source_type = 'text';
        $foreign->content = 'Notes that belong to another SBU.';
        $foreign->approval_status = 'approved';
        $foreign->save();

        $this->actingAs($admin, 'admin')
            ->withSession(['selected_tenant_id' => $tenant->id])
            ->get('/admin/knowledge')
            ->assertOk()
            ->assertSee('knowledgeFormCanvas', false)
            ->assertSee('Add a document')
            ->assertSee('Documents for the-inner-cove')
            ->assertDontSee('Corondeli notes');

        $this->actingAs($admin, 'admin')
            ->withSession(['selected_tenant_id' => 'general'])
            ->from('/admin/knowledge')
            ->post('/admin/knowledge', [
                'title' => 'Should not save',
                'content' => 'This has no selected SBU.',
            ])
            ->assertRedirect('/admin/knowledge')
            ->assertSessionHas('error');

        $this->actingAs($admin, 'admin')
            ->withSession(['selected_tenant_id' => $tenant->id])
            ->from('/admin/knowledge')
            ->post('/admin/knowledge', [
                'tenant_id' => $other->id,
                'title' => 'Gate hours',
                'content' => 'The gate closes at 22:00.',
                'file' => UploadedFile::fake()->createWithContent('hours.txt', 'Guests should arrive before 22:00.'),
            ])
            ->assertRedirect('/admin/knowledge')
            ->assertSessionHas('success');

        $document = AiKnowledgeDocument::query()->where('title', 'Gate hours')->firstOrFail();
        $this->assertSame($tenant->id, $document->tenant_id);
        $this->assertSame('The gate closes at 22:00.', $document->content);
        $this->assertSame('pending', $document->approval_status);
        $this->assertNotNull($document->source_reference);
        Storage::disk('local')->assertExists($document->source_reference);
        $this->assertSame(1, $document->chunks()->count());

        $this->actingAs($admin, 'admin')
            ->withSession(['selected_tenant_id' => $tenant->id])
            ->get('/admin/knowledge/'.$document->id.'/file')
            ->assertOk();

        $this->actingAs($admin, 'admin')
            ->withSession(['selected_tenant_id' => $tenant->id])
            ->from('/admin/knowledge')
            ->patch('/admin/knowledge/'.$document->id, [
                'title' => 'Gate hours',
                'content' => 'The gate closes at 21:00.',
            ])
            ->assertRedirect('/admin/knowledge');

        $document->refresh();
        $this->assertSame('The gate closes at 21:00.', $document->content);
        $this->assertSame('pending', $document->approval_status);
        $this->assertSame($tenant->id, $document->tenant_id);

        $this->actingAs($admin, 'admin')
            ->withSession(['selected_tenant_id' => $tenant->id])
            ->from('/admin/knowledge')
            ->delete('/admin/knowledge/'.$document->id)
            ->assertRedirect('/admin/knowledge');

        $this->assertNull(AiKnowledgeDocument::query()->find($document->id));
        Storage::disk('local')->assertMissing($document->source_reference);
    }

    public function test_a_text_file_can_supply_the_document_text(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();
        $tenant = $this->makeTenant('the-inner-cove');

        $this->actingAs($admin, 'admin')
            ->withSession(['selected_tenant_id' => $tenant->id])
            ->post('/admin/knowledge', [
                'title' => 'Rate note',
                'file' => UploadedFile::fake()->createWithContent('rates.txt', 'Eight guests are included in the nightly rate.'),
            ])
            ->assertRedirect();

        $document = AiKnowledgeDocument::query()->where('title', 'Rate note')->firstOrFail();
        $this->assertSame('Eight guests are included in the nightly rate.', $document->content);
        $this->assertSame($tenant->id, $document->tenant_id);
    }

    public function test_an_operator_cannot_manage_another_sbu_document(): void
    {
        Storage::fake('local');
        $tenant = $this->makeTenant('the-inner-cove');
        $other = $this->makeTenant('corondeli');
        $admin = $this->makeAdmin(['username' => 'cove-operator', 'admin_type' => 1, 'access_scope' => 'assigned']);
        $this->assign($admin, $tenant, 'sbu_operator');

        $document = app(KnowledgeService::class)->store(
            $other,
            'Private notes',
            'Only Corondeli can read this file.',
            UploadedFile::fake()->createWithContent('private.txt', 'Only Corondeli can read this file.'),
        );

        $this->actingAs($admin->fresh(), 'admin')
            ->withSession(['selected_tenant_id' => $tenant->id])
            ->post('/admin/knowledge', [
                'title' => 'Blocked',
                'content' => 'An operator cannot store this.',
            ])
            ->assertForbidden();

        $this->actingAs($admin->fresh(), 'admin')
            ->withSession(['selected_tenant_id' => $tenant->id])
            ->get('/admin/knowledge/'.$document->id.'/file')
            ->assertForbidden();

        $this->actingAs($admin->fresh(), 'admin')
            ->patch('/admin/knowledge/'.$document->id, [
                'title' => 'Private notes',
                'content' => 'Changed.',
            ])
            ->assertNotFound();
    }
}
