<?php

namespace Tests\Feature;

use App\Models\SimBooking;
use Tests\PlatformTestCase;

class BookingSliceTest extends PlatformTestCase
{
    public function test_a_booking_instruction_clarifies_then_waits_for_one_approval(): void
    {
        $admin = $this->makeAdmin();
        $tenant = $this->makeTenant('the-inner-cove', true);
        $this->actingAs($admin, 'admin');

        $this->post('/admin/workspace', ['tenant_id' => $tenant->id])->assertRedirect();
        $conversation = $tenant->conversations()->firstOrFail();

        $this->post('/admin/workspace/'.$conversation->id.'/messages', [
            'body' => 'Create a booking for Maria Santos from November 28 to 30 for six guests.',
        ])->assertRedirect();

        $operation = $conversation->operations()->firstOrFail();
        $this->assertSame('booking.create', $operation->intent);
        $this->assertSame('awaiting_clarification', $operation->status);
        $this->assertSame('Maria Santos', $operation->parameters['name']);
        $this->assertSame('2026-11-28', $operation->parameters['arrival']);
        $this->assertSame('2026-11-30', $operation->parameters['departure']);
        $this->assertSame(6, (int) $operation->parameters['guests']);
        $this->assertContains('email', $operation->missing_fields);
        $this->assertContains('mobile', $operation->missing_fields);
        $this->assertLessThan(0.90, (float) $operation->confidence_score);
        $this->assertSame(0, SimBooking::query()->count());

        $this->get('/admin/workspace/'.$conversation->id)
            ->assertOk()
            ->assertSee('What email address and mobile number')
            ->assertSee('Uncalibrated cap');

        $this->post('/admin/workspace/'.$conversation->id.'/messages', [
            'fields' => [
                'email' => 'maria.santos@example.test',
                'mobile' => '09171234567',
            ],
        ])->assertRedirect();

        $operation->refresh();
        $this->assertSame('awaiting_approval', $operation->status);
        $this->assertSame(0, SimBooking::query()->count());

        $approval = $operation->approvals()->where('status', 'pending')->firstOrFail();
        $this->post('/admin/approvals/'.$approval->id.'/approve', ['reason' => 'Checked the quote.'])
            ->assertRedirect();

        $this->assertSame(1, SimBooking::query()->count());
        $booking = SimBooking::query()->firstOrFail();
        $this->assertSame('Maria Santos', $booking->guest_name);
        $this->assertSame('2026-11-28', $booking->arrival->toDateString());
        $this->assertSame('pending', $booking->status);
        $operation->refresh();
        $this->assertSame('completed', $operation->status);

        $this->from('/admin/workspace/'.$conversation->id)
            ->post('/admin/approvals/'.$approval->id.'/approve', ['reason' => 'Again'])
            ->assertRedirect('/admin/workspace/'.$conversation->id)
            ->assertSessionHas('error');
        $this->assertSame(1, SimBooking::query()->count());

        $this->post('/admin/workspace/'.$conversation->id.'/messages', [
            'body' => 'Create a booking for Maria Santos from November 28 to 30 for six guests. Email maria.santos@example.test mobile 09171234567.',
        ])->assertRedirect();
        $this->assertSame(1, SimBooking::query()->count());
    }

    public function test_overlapping_dates_do_not_create_a_second_booking(): void
    {
        $admin = $this->makeAdmin();
        $tenant = $this->makeTenant('the-inner-cove', true);
        $this->actingAs($admin, 'admin');
        $this->post('/admin/workspace', ['tenant_id' => $tenant->id]);
        $first = $tenant->conversations()->firstOrFail();
        $this->post('/admin/workspace/'.$first->id.'/messages', [
            'body' => 'Create a booking for Maria Santos from November 28 to 30 for six guests. Email maria.santos@example.test mobile 09171234567.',
        ]);
        $approval = $first->operations()->firstOrFail()->approvals()->firstOrFail();
        $this->post('/admin/approvals/'.$approval->id.'/approve', ['reason' => 'Approved']);
        $this->assertSame(1, SimBooking::query()->count());

        $this->post('/admin/workspace', ['tenant_id' => $tenant->id]);
        $second = $tenant->conversations()->whereKeyNot($first->id)->firstOrFail();
        $this->post('/admin/workspace/'.$second->id.'/messages', [
            'body' => 'Create a booking for Ana Reyes from November 28 to 30 for four guests. Email ana.reyes@example.test mobile 09179876543.',
        ])->assertRedirect();

        $this->assertSame('failed', $second->operations()->firstOrFail()->status);
        $this->assertSame(1, SimBooking::query()->count());
    }

    public function test_a_viewer_cannot_send_an_instruction(): void
    {
        $viewer = $this->makeAdmin(['username' => 'viewer', 'admin_type' => 1, 'access_scope' => 'assigned']);
        $tenant = $this->makeTenant('the-inner-cove', true);
        $this->assign($viewer, $tenant, 'sbu_viewer');
        $operator = $this->makeAdmin(['username' => 'owner']);
        $this->actingAs($operator, 'admin');
        $this->post('/admin/workspace', ['tenant_id' => $tenant->id]);
        $conversation = $tenant->conversations()->firstOrFail();

        $this->actingAs($viewer->fresh(), 'admin')
            ->post('/admin/workspace/'.$conversation->id.'/messages', ['body' => 'Create a booking for Maria Santos.'])
            ->assertForbidden();
        $this->assertSame(0, $conversation->operations()->count());
    }
}
