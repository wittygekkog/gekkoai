<?php

namespace Tests\Feature;

use App\Models\SimBooking;
use Illuminate\Support\Str;
use Tests\PlatformTestCase;
use WittyGekko\SbuConnector\Security\RequestSigner;

class ConnectorSecurityTest extends PlatformTestCase
{
    public function test_unsigned_execution_is_rejected_and_validation_does_not_write(): void
    {
        $tenant = $this->makeTenant('the-inner-cove', true);
        $admin = $this->makeAdmin();

        $this->postJson('/api/ai/v1/operations/execute', $this->payload($tenant->id))
            ->assertUnauthorized();
        $this->assertSame(0, SimBooking::query()->count());

        $this->signed('POST', '/api/ai/v1/operations/validate', $this->payload($tenant->id), $admin->id, $tenant->id)
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertSame(0, SimBooking::query()->count());
    }

    public function test_a_signed_write_is_idempotent_and_a_changed_payload_conflicts(): void
    {
        $tenant = $this->makeTenant('the-inner-cove', true);
        $admin = $this->makeAdmin();
        $payload = $this->payload($tenant->id);
        $payload['operation'] = 'booking.create';
        $payload['idempotency_key'] = 'booking-once';
        $payload['parameters'] = [
            'name' => 'Maria Santos',
            'email' => 'maria.santos@example.test',
            'mobile' => '09171234567',
            'arrival' => '2026-12-10',
            'departure' => '2026-12-12',
            'guests' => 6,
        ];

        $this->signed('POST', '/api/ai/v1/operations/execute', $payload, $admin->id, $tenant->id)
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertSame(1, SimBooking::query()->count());

        $this->signed('POST', '/api/ai/v1/operations/execute', $payload, $admin->id, $tenant->id)
            ->assertOk();
        $this->assertSame(1, SimBooking::query()->count());

        $changed = $payload;
        $changed['parameters']['guests'] = 4;
        $this->signed('POST', '/api/ai/v1/operations/execute', $changed, $admin->id, $tenant->id)
            ->assertStatus(409);
        $this->assertSame(1, SimBooking::query()->count());
    }

    public function test_a_replayed_nonce_is_rejected(): void
    {
        $tenant = $this->makeTenant('the-inner-cove', true);
        $admin = $this->makeAdmin();
        $payload = $this->payload($tenant->id);
        $nonce = (string) Str::uuid();

        $this->signed('POST', '/api/ai/v1/operations/validate', $payload, $admin->id, $tenant->id, $nonce)
            ->assertOk();
        $this->signed('POST', '/api/ai/v1/operations/validate', $payload, $admin->id, $tenant->id, $nonce)
            ->assertStatus(409);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signed(string $method, string $path, array $payload, string $actorId, string $tenantId, ?string $nonce = null)
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $nonce ??= (string) Str::uuid();
        $signature = app(RequestSigner::class)->sign(
            'test-connector-secret',
            $timestamp,
            $nonce,
            $method,
            $path,
            $body,
            $actorId,
            $tenantId,
        );

        return $this->call($method, $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SBU_KEY' => 'sim-inner-cove',
            'HTTP_X_SBU_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_SBU_NONCE' => $nonce,
            'HTTP_X_SBU_SIGNATURE' => $signature,
            'HTTP_X_SBU_ACTOR' => $actorId,
            'HTTP_X_SBU_TENANT' => $tenantId,
        ], $body);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $tenantId): array
    {
        return [
            'request_id' => (string) Str::uuid(),
            'operation_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'operation' => 'booking.check_availability',
            'operation_version' => '1.0',
            'parameters' => [
                'arrival' => '2026-12-01',
                'departure' => '2026-12-03',
                'guests' => 4,
            ],
            'idempotency_key' => 'read-'.Str::uuid(),
            'approval_reference' => null,
            'correlation_id' => (string) Str::uuid(),
        ];
    }
}
