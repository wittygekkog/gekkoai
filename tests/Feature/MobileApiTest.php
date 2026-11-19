<?php

namespace Tests\Feature;

use App\Models\AiKnowledgeDocument;
use App\Models\AiLogicItem;
use App\Models\AiOperationRequest;
use App\Models\SimBooking;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\PlatformTestCase;

class MobileApiTest extends PlatformTestCase
{
    public function test_branding_is_public_and_login_rejects_bad_credentials(): void
    {
        $branding = $this->getJson('/api/v1/branding')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.branding.app_name', 'GekkoAi')
            ->assertJsonPath('data.branding.primary_color', '#7C3AED');
        $this->assertStringContainsString('branding/gekkoai-logo.png', (string) $branding->json('data.branding.logo_url'));

        $admin = $this->makeAdmin(['email' => 'ada@example.test']);
        $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized()
            ->assertJsonPath('data.code', 'invalid_credentials');
    }

    public function test_login_returns_a_token_and_logout_revokes_it(): void
    {
        $admin = $this->makeAdmin(['email' => 'ada@example.test']);
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'ada@example.test',
            'password' => 'secret1',
            'remember' => true,
            'device_name' => 'Pixel',
        ])->assertOk();

        $token = $response->json('data.access_token');
        $this->assertIsString($token);
        $this->assertStringStartsWith('gekko_', $token);
        $this->assertStringNotContainsString('secret1', $response->getContent());
        $response->assertJsonPath('data.admin.email', $admin->email);

        $this->withToken($token)->getJson('/api/v1/profile')->assertOk()->assertJsonPath('data.admin.email', $admin->email);
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->withToken($token)->getJson('/api/v1/profile')->assertUnauthorized();
    }

    public function test_an_expired_or_missing_token_is_rejected(): void
    {
        $this->getJson('/api/v1/tenants')->assertUnauthorized();
        $this->withToken('gekko_not-a-real-token')->getJson('/api/v1/tenants')->assertUnauthorized();
    }

    public function test_tenant_list_is_isolated_and_logos_are_urls(): void
    {
        $home = $this->makeTenant('the-inner-cove', true);
        $home->description = 'Coron retreat';
        $home->logo_path = 'tenants/inner.png';
        $home->save();
        $other = $this->makeTenant('pearl-vista', false);
        $assigned = $this->makeAdmin(['username' => 'cove-only', 'email' => 'cove@example.test', 'admin_type' => 1, 'access_scope' => 'assigned']);
        $this->assign($assigned, $home, 'sbu_operator');

        $token = $this->tokenFor($assigned);
        $response = $this->withToken($token)->getJson('/api/v1/tenants')->assertOk();
        $ids = collect($response->json('data.tenants'))->pluck('id');
        $this->assertTrue($ids->contains($home->id));
        $this->assertFalse($ids->contains($other->id));
        $this->assertStringContainsString('inner.png', (string) $response->json('data.tenants.0.logo_url'));

        $this->withToken($token)->getJson('/api/v1/access?tenant_id='.$other->id)->assertForbidden();
    }

    public function test_a_message_clarifies_and_refresh_does_not_repeat_the_operation(): void
    {
        $admin = $this->makeAdmin();
        $tenant = $this->makeTenant('the-inner-cove', true);
        $token = $this->tokenFor($admin);

        $created = $this->withToken($token)->postJson('/api/v1/conversations', [
            'tenant_id' => $tenant->id,
        ])->assertCreated();
        $conversationId = $created->json('data.conversation.id');

        $sent = $this->withToken($token)->postJson('/api/v1/conversations/'.$conversationId.'/messages', [
            'body' => 'Create a booking for Maria Santos from November 28 to 30 for six guests.',
        ])->assertOk();

        $sent->assertJsonPath('data.latest_operation.intent', 'booking.create');
        $sent->assertJsonPath('data.latest_operation.status', 'awaiting_clarification');
        $this->assertSame(1, AiOperationRequest::query()->count());
        $this->assertSame(0, SimBooking::query()->count());
        $messageIds = collect($sent->json('data.messages'))->pluck('id');
        $this->assertSame($messageIds->count(), $messageIds->unique()->count());

        $this->withToken($token)->getJson('/api/v1/conversations/'.$conversationId)->assertOk();
        $this->withToken($token)->getJson('/api/v1/conversations/'.$conversationId.'/logic')->assertOk()
            ->assertJsonPath('data.intent', 'booking.create');
        $this->assertSame(1, AiOperationRequest::query()->count());
        $this->assertSame(0, SimBooking::query()->count());
    }

    public function test_audio_upload_does_not_execute_a_booking(): void
    {
        $admin = $this->makeAdmin();
        $tenant = $this->makeTenant('the-inner-cove', true);
        $token = $this->tokenFor($admin);
        $conversationId = $this->withToken($token)->postJson('/api/v1/conversations', [
            'tenant_id' => $tenant->id,
        ])->json('data.conversation.id');

        $this->withToken($token)->post('/api/v1/conversations/'.$conversationId.'/audio', [
            'audio' => UploadedFile::fake()->createWithContent('note.wav', $this->wav()),
            'language' => 'tl',
        ])->assertOk()
            ->assertJsonPath('data.transcription.provider_configured', false)
            ->assertJsonPath('data.transcription.language', 'tl')
            ->assertJsonPath('data.transcription.transcript', null);

        $this->assertSame(0, AiOperationRequest::query()->count());
        $this->assertSame(0, SimBooking::query()->count());
    }

    public function test_configured_speech_fills_the_transcript_without_creating_a_booking(): void
    {
        config([
            'gekko.speech_provider' => 'openai',
            'services.openai.key' => 'test-key',
            'services.openai.speech_model' => 'whisper-1',
            'services.openai.base_url' => 'https://api.openai.com/v1',
        ]);
        Http::fake([
            'api.openai.com/*' => Http::response(['text' => 'Check availability for two guests.'], 200),
        ]);

        $admin = $this->makeAdmin();
        $tenant = $this->makeTenant('the-inner-cove', true);
        $token = $this->tokenFor($admin);
        $conversationId = $this->withToken($token)->postJson('/api/v1/conversations', [
            'tenant_id' => $tenant->id,
        ])->json('data.conversation.id');

        $this->withToken($token)->post('/api/v1/conversations/'.$conversationId.'/audio', [
            'audio' => UploadedFile::fake()->createWithContent('note.wav', $this->wav()),
            'language' => 'en',
        ])->assertOk()
            ->assertJsonPath('data.transcription.provider_configured', true)
            ->assertJsonPath('data.transcription.transcript', 'Check availability for two guests.')
            ->assertJsonPath('data.transcription.status', 'transcribed');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.openai.com/v1/audio/transcriptions'
            && $request->hasHeader('Authorization', 'Bearer test-key'));

        $this->assertSame(0, AiOperationRequest::query()->count());
        $this->assertSame(0, SimBooking::query()->count());
    }

    public function test_a_viewer_cannot_send_and_cannot_read_another_tenants_conversation(): void
    {
        $tenant = $this->makeTenant('the-inner-cove', true);
        $other = $this->makeTenant('pearl-vista', true);
        $owner = $this->makeAdmin(['username' => 'owner']);
        $viewer = $this->makeAdmin(['username' => 'viewer', 'email' => 'viewer@example.test', 'admin_type' => 1, 'access_scope' => 'assigned']);
        $this->assign($viewer, $tenant, 'sbu_viewer');

        $ownerToken = $this->tokenFor($owner);
        $foreignId = $this->withToken($ownerToken)->postJson('/api/v1/conversations', [
            'tenant_id' => $other->id,
        ])->json('data.conversation.id');

        $viewerToken = $this->tokenFor($viewer);
        $this->withToken($viewerToken)->getJson('/api/v1/conversations/'.$foreignId)->assertForbidden();

        $ownId = $this->withToken($ownerToken)->postJson('/api/v1/conversations', [
            'tenant_id' => $tenant->id,
        ])->json('data.conversation.id');
        $this->withToken($viewerToken)->postJson('/api/v1/conversations/'.$ownId.'/messages', [
            'body' => 'Create a booking for Maria Santos.',
        ])->assertForbidden();
        $this->assertSame(0, AiOperationRequest::query()->count());
    }

    public function test_rules_and_documents_match_the_admin_screens(): void
    {
        $tenant = $this->makeTenant('the-inner-cove');
        $other = $this->makeTenant('pearl-vista');
        $admin = $this->makeAdmin(['username' => 'logic-admin', 'email' => 'logic@example.test']);
        $viewer = $this->makeAdmin(['username' => 'logic-viewer', 'email' => 'logic-viewer@example.test']);
        $this->assign($viewer, $tenant, 'sbu_viewer');
        $token = $this->tokenFor($admin);
        $viewerToken = $this->tokenFor($viewer);

        $ruleId = $this->withToken($token)->postJson('/api/v1/rules', [
            'tenant_id' => $tenant->id,
            'title' => 'Ask for the guest name',
            'body' => 'Always ask for the guest name before booking.',
        ])->assertCreated()->json('data.rule.id');

        $this->withToken($token)->getJson('/api/v1/rules?tenant_id='.$tenant->id)
            ->assertOk()
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.rules.data.0.id', $ruleId)
            ->assertJsonPath('data.rules.data.0.status', 'active');

        $this->withToken($token)->patchJson('/api/v1/rules/'.$ruleId, [
            'title' => 'Ask for the guest name',
            'body' => 'Ask for the guest name and the stay dates.',
        ])->assertOk()->assertJsonPath('data.rule.body', 'Ask for the guest name and the stay dates.');

        $this->withToken($viewerToken)->getJson('/api/v1/rules?tenant_id='.$tenant->id)
            ->assertOk()
            ->assertJsonPath('data.can_manage', false);
        $this->withToken($viewerToken)->postJson('/api/v1/rules', [
            'tenant_id' => $tenant->id,
            'title' => 'Blocked',
            'body' => 'A viewer cannot write rules.',
        ])->assertForbidden();
        $this->withToken($viewerToken)->getJson('/api/v1/rules?tenant_id='.$other->id)->assertForbidden();

        $documentId = $this->withToken($token)->postJson('/api/v1/documents', [
            'tenant_id' => $tenant->id,
            'title' => 'House rules',
            'content' => 'Quiet hours start at 22:00.',
        ])->assertCreated()
            ->assertJsonPath('data.document.approval_status', 'pending')
            ->json('data.document.id');

        $listed = $this->withToken($token)->getJson('/api/v1/documents?tenant_id='.$tenant->id)
            ->assertOk()
            ->assertJsonPath('data.documents.data.0.approval_status', 'pending')
            ->json('data.documents.data.0');
        $this->assertArrayNotHasKey('content', $listed);
        $this->assertArrayNotHasKey('source_reference', $listed);
        $this->assertSame('pending', AiKnowledgeDocument::query()->find($documentId)->approval_status);

        $this->withToken($token)->getJson('/api/v1/documents/'.$documentId)
            ->assertOk()
            ->assertJsonPath('data.document.content', 'Quiet hours start at 22:00.');

        $this->withToken($token)->postJson('/api/v1/documents/'.$documentId.'/approve')
            ->assertOk()
            ->assertJsonPath('data.document.approval_status', 'approved');
        $this->withToken($token)->postJson('/api/v1/documents/'.$documentId.'/approve')
            ->assertOk()
            ->assertJsonPath('data.document.approval_status', 'approved');

        $this->withToken($token)->patchJson('/api/v1/documents/'.$documentId, [
            'title' => 'House rules',
            'content' => 'Quiet hours start at 21:00.',
        ])->assertOk()->assertJsonPath('data.document.approval_status', 'pending');

        $this->withToken($viewerToken)->deleteJson('/api/v1/documents/'.$documentId)->assertForbidden();
        $this->withToken($token)->deleteJson('/api/v1/documents/'.$documentId)->assertOk();
        $this->assertSame(0, AiKnowledgeDocument::query()->count());
        $this->assertSame(1, AiLogicItem::query()->count());
    }

    public function test_rule_chat_drafts_the_same_way_as_the_admin_screen(): void
    {
        $tenant = $this->makeTenant('the-inner-cove');
        $other = $this->makeTenant('pearl-vista');
        $admin = $this->makeAdmin(['username' => 'rule-chat', 'email' => 'rule-chat@example.test']);
        $viewer = $this->makeAdmin(['username' => 'rule-chat-viewer', 'email' => 'rule-chat-viewer@example.test']);
        $this->assign($viewer, $tenant, 'sbu_viewer');
        $token = $this->tokenFor($admin);

        $draft = $this->withToken($token)->postJson('/api/v1/rules/chat', [
            'tenant_id' => $tenant->id,
            'message' => 'Always ask for the guest name before booking',
        ])->assertOk()
            ->assertJsonPath('data.title', 'Always ask for the guest name before booking')
            ->assertJsonPath('data.body', 'Always ask for the guest name before booking.');

        $ruleId = $this->withToken($token)->postJson('/api/v1/rules', [
            'tenant_id' => $tenant->id,
            'title' => $draft->json('data.title'),
            'body' => $draft->json('data.body'),
        ])->assertCreated()->json('data.rule.id');

        $this->withToken($token)->postJson('/api/v1/rules/chat', [
            'tenant_id' => $tenant->id,
            'rule_id' => $ruleId,
            'title' => $draft->json('data.title'),
            'body' => $draft->json('data.body'),
            'message' => 'change the statement to ask for the stay dates',
        ])->assertOk()
            ->assertJsonPath('data.title', 'Always ask for the guest name before booking')
            ->assertJsonPath('data.body', 'ask for the stay dates.');

        $this->withToken($this->tokenFor($viewer))->postJson('/api/v1/rules/chat', [
            'tenant_id' => $tenant->id,
            'message' => 'A viewer cannot draft a rule',
        ])->assertForbidden();
        $this->withToken($this->tokenFor($viewer))->postJson('/api/v1/rules/chat', [
            'tenant_id' => $other->id,
            'message' => 'Another business unit',
        ])->assertForbidden();
        $this->assertSame(1, AiLogicItem::query()->count());
    }

    public function test_objectives_match_the_admin_screen(): void
    {
        $tenant = $this->makeTenant('the-inner-cove');
        $other = $this->makeTenant('pearl-vista');
        $admin = $this->makeAdmin(['username' => 'objective-admin', 'email' => 'objective@example.test']);
        $viewer = $this->makeAdmin(['username' => 'objective-viewer', 'email' => 'objective-viewer@example.test']);
        $this->assign($viewer, $tenant, 'sbu_viewer');
        $token = $this->tokenFor($admin);
        $viewerToken = $this->tokenFor($viewer);

        $draft = $this->withToken($token)->postJson('/api/v1/objectives/chat', [
            'tenant_id' => $tenant->id,
            'message' => 'We really just want to help guests book easily and skip approval.',
        ])->assertOk()
            ->assertJsonPath('data.title', 'Help guests book easily')
            ->assertJsonPath('data.body', 'Help guests book easily.')
            ->assertJsonPath('message', 'I kept this to one outcome and removed the part that would skip Rules or approval.');

        $objectiveId = $this->withToken($token)->postJson('/api/v1/objectives', [
            'tenant_id' => $tenant->id,
            'title' => $draft->json('data.title'),
            'body' => $draft->json('data.body'),
        ])->assertCreated()->json('data.objective.id');

        $stored = AiLogicItem::query()->findOrFail($objectiveId);
        $this->assertSame('objective', $stored->kind);
        $this->assertSame($tenant->id, $stored->tenant_id);

        $this->withToken($token)->getJson('/api/v1/objectives?tenant_id='.$tenant->id)
            ->assertOk()
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.objectives.data.0.id', $objectiveId)
            ->assertJsonPath('data.objectives.data.0.body', 'Help guests book easily.');
        $this->withToken($token)->getJson('/api/v1/rules?tenant_id='.$tenant->id)
            ->assertOk()
            ->assertJsonPath('data.rules.data', []);

        $this->withToken($token)->postJson('/api/v1/objectives/chat', [
            'tenant_id' => $tenant->id,
            'objective_id' => $objectiveId,
            'message' => 'Improve this.',
        ])->assertOk()
            ->assertJsonPath('data.title', 'Help guests book easily')
            ->assertJsonPath('data.body', 'Help guests book easily.');

        $this->withToken($token)->patchJson('/api/v1/objectives/'.$objectiveId, [
            'title' => 'Help guests finish a booking',
            'body' => 'Help guests finish a booking.',
        ])->assertOk()
            ->assertJsonPath('data.objective.body', 'Help guests finish a booking.');

        $ruleId = $this->withToken($token)->postJson('/api/v1/rules', [
            'tenant_id' => $tenant->id,
            'title' => 'Ask for the guest name',
            'body' => 'Always ask for the guest name before booking.',
        ])->assertCreated()->json('data.rule.id');
        $this->withToken($token)->patchJson('/api/v1/objectives/'.$ruleId, [
            'title' => 'Ask for the guest name',
            'body' => 'This must stay a rule.',
        ])->assertNotFound();

        $this->withToken($viewerToken)->getJson('/api/v1/objectives?tenant_id='.$tenant->id)
            ->assertOk()
            ->assertJsonPath('data.can_manage', false);
        $this->withToken($viewerToken)->postJson('/api/v1/objectives/chat', [
            'tenant_id' => $tenant->id,
            'message' => 'A viewer cannot write an objective',
        ])->assertForbidden();
        $this->withToken($viewerToken)->postJson('/api/v1/objectives', [
            'tenant_id' => $tenant->id,
            'title' => 'Blocked',
            'body' => 'A viewer cannot save this.',
        ])->assertForbidden();
        $this->withToken($viewerToken)->postJson('/api/v1/objectives/chat', [
            'tenant_id' => $other->id,
            'message' => 'Another business unit',
        ])->assertForbidden();
        $this->assertSame(1, AiLogicItem::query()->where('kind', 'objective')->count());
        $this->assertSame('Always ask for the guest name before booking.', AiLogicItem::query()->findOrFail($ruleId)->body);
    }

    public function test_document_file_upload_matches_the_admin_form(): void
    {
        Storage::fake('local');
        $tenant = $this->makeTenant('the-inner-cove');
        $admin = $this->makeAdmin(['username' => 'data-admin', 'email' => 'data@example.test']);
        $token = $this->tokenFor($admin);

        $this->withToken($token)->post('/api/v1/documents', [
            'tenant_id' => $tenant->id,
            'title' => 'Missing text',
        ])->assertStatus(422);

        $created = $this->withToken($token)->post('/api/v1/documents', [
            'tenant_id' => $tenant->id,
            'title' => 'House rules',
            'content' => '',
            'file' => UploadedFile::fake()->createWithContent('house-rules.txt', "Quiet hours start at 22:00.\n"),
        ])->assertCreated()
            ->assertJsonPath('data.document.has_file', true)
            ->assertJsonPath('data.document.approval_status', 'pending');

        $documentId = $created->json('data.document.id');
        $stored = AiKnowledgeDocument::query()->findOrFail($documentId);
        $this->assertStringContainsString('Quiet hours start at 22:00.', (string) $stored->content);
        $this->assertStringStartsWith('knowledge/', (string) $stored->source_reference);
        Storage::disk('local')->assertExists($stored->source_reference);

        $this->withToken($token)->post('/api/v1/documents/'.$documentId, [
            'title' => 'Updated house rules',
            'content' => 'Quiet hours start at 21:00.',
            'file' => UploadedFile::fake()->createWithContent('updated-rules.txt', "Quiet hours start at 21:00.\n"),
        ])->assertOk()
            ->assertJsonPath('data.document.title', 'Updated house rules')
            ->assertJsonPath('data.document.approval_status', 'pending')
            ->assertJsonPath('data.document.content', 'Quiet hours start at 21:00.');
    }

    private function tokenFor(\App\Models\Admin $admin): string
    {
        return (string) $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'secret1',
        ])->assertOk()->json('data.access_token');
    }

    private function wav(): string
    {
        $pcm = str_repeat("\0", 32);
        $size = 36 + strlen($pcm);

        return 'RIFF'.pack('V', $size).'WAVEfmt '.pack('V', 16)
            .pack('v', 1).pack('v', 1).pack('V', 8000).pack('V', 8000)
            .pack('v', 1).pack('v', 8).'data'.pack('V', strlen($pcm)).$pcm;
    }
}
