<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\Admin;
use App\Models\AiApproval;
use App\Models\AiClarification;
use App\Models\AiConversation;
use App\Models\AiEscalation;
use App\Models\AiMessage;
use App\Models\AiOperationRequest;
use App\Models\AiTranscription;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\TenantConnector;
use App\Services\AccessService;
use Illuminate\Support\Facades\Storage;

/**
 * Shapes API payloads for the Android client.
 * Connector credentials and chain-of-thought are never included.
 */
final class PlatformPayload
{
    public function __construct(private readonly AccessService $access) {}

    /**
     * @return array<string, mixed>
     */
    public function branding(): array
    {
        $logo = Setting::query()->where('setting_slug', 'site-logo')->first();
        $name = getSetting('site-name', 'GekkoAi') ?: 'GekkoAi';
        $color = getSetting('theme-primary', '#7C3AED') ?: '#7C3AED';
        $configured = $this->publicUrl($logo?->setting_value);
        $defaultPath = public_path('branding/gekkoai-logo.png');
        $logoUrl = $configured;
        $version = $logo?->updated_at?->getTimestamp() ?? 0;
        if ($logoUrl === null && is_file($defaultPath)) {
            $logoUrl = url('/branding/gekkoai-logo.png');
            $version = filemtime($defaultPath) ?: 1;
        }
        $logoUrl=url(getSetting('site-logo', 'branding/gekkoai-logo.png'));
        return [
            'app_name' => $name,
            'logo_url' => $logoUrl,
            'primary_color' => $this->color($color),
            'logo_version' => $version,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function tenant(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'description' => $tenant->description,
            'status' => $tenant->status,
            'timezone' => $tenant->timezone,
            'locale' => $tenant->locale,
            'logo_url' => $this->publicUrl($tenant->logo_path),
            'logo_version' => $tenant->updated_at?->getTimestamp() ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function conversation(AiConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'tenant_id' => $conversation->tenant_id,
            'title' => $conversation->title,
            'status' => $conversation->status,
            'updated_at' => $conversation->updated_at?->toIso8601String(),
            'created_at' => $conversation->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function message(AiMessage $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'role' => $message->role,
            'message_type' => $message->message_type,
            'content' => $message->content,
            'structured_content' => $message->structured_content,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function operation(AiOperationRequest $operation, bool $detailed = false): array
    {
        $operation->loadMissing(['tenant', 'approvals', 'clarifications', 'executions', 'steps']);
        $payload = [
            'id' => $operation->id,
            'tenant_id' => $operation->tenant_id,
            'tenant_name' => $operation->tenant?->name,
            'conversation_id' => $operation->conversation_id,
            'status' => $operation->status,
            'intent' => $operation->intent,
            'parameters' => $operation->parameters ?? [],
            'missing_fields' => $operation->missing_fields ?? [],
            'confidence_score' => $operation->confidence_score,
            'confidence' => $this->confidence($operation->confidence_metadata),
            'calibration_version' => $operation->calibration_version,
            'risk_level' => $operation->risk_level,
            'execution_mode' => $operation->execution_mode,
            'original_instruction' => $operation->original_instruction,
            'updated_at' => $operation->updated_at?->toIso8601String(),
            'approvals' => $operation->approvals->map(fn (AiApproval $approval): array => $this->approval($approval))->values(),
            'clarifications' => $operation->clarifications->map(fn (AiClarification $row): array => [
                'id' => $row->id,
                'question' => $row->question,
                'expected_fields' => $row->expected_fields ?? [],
                'response' => $row->response,
                'status' => $row->status,
                'round_number' => $row->round_number,
            ])->values(),
            'results' => $operation->executions->map(fn ($execution): array => [
                'id' => $execution->id,
                'status' => $execution->status,
                'error_code' => $execution->error_code,
                'data' => is_array($execution->response_data) ? ($execution->response_data['data'] ?? $execution->response_data) : null,
                'executed_at' => $execution->executed_at?->toIso8601String(),
            ])->values(),
        ];
        if ($detailed) {
            $payload['steps'] = $operation->steps->map(fn ($step): array => [
                'step_number' => $step->step_number,
                'operation_name' => $step->operation_name,
                'status' => $step->status,
                'result' => $step->result,
            ])->values();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function approval(AiApproval $approval): array
    {
        return [
            'id' => $approval->id,
            'tenant_id' => $approval->tenant_id,
            'operation_request_id' => $approval->operation_request_id,
            'status' => $approval->status,
            'decision_reason' => $approval->decision_reason,
            'expires_at' => $approval->expires_at?->toIso8601String(),
            'snapshot' => $approval->approval_snapshot,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function escalation(AiEscalation $escalation): array
    {
        return [
            'id' => $escalation->id,
            'tenant_id' => $escalation->tenant_id,
            'conversation_id' => $escalation->conversation_id,
            'operation_request_id' => $escalation->operation_request_id,
            'reason' => $escalation->reason,
            'priority' => $escalation->priority,
            'status' => $escalation->status,
            'resolution' => $escalation->resolution,
            'created_at' => $escalation->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transcription(AiTranscription $transcription): array
    {
        $meta = is_array($transcription->transcription_metadata) ? $transcription->transcription_metadata : [];

        return [
            'id' => $transcription->id,
            'transcript' => $transcription->transcript,
            'corrected_transcript' => $transcription->corrected_transcript,
            'language' => $transcription->language,
            'status' => (string) ($meta['status'] ?? 'stored'),
            'provider_configured' => (bool) ($meta['provider_configured'] ?? false),
            'supported_languages' => $meta['supported_languages'] ?? ['en', 'tl'],
            'detail' => (string) ($meta['detail'] ?? ''),
        ];
    }

    /**
     * Full conversation read used by the workspace and by pull-to-refresh.
     *
     * @return array<string, mixed>
     */
    public function workspace(AiConversation $conversation): array
    {
        $conversation->loadMissing(['messages', 'operations.approvals', 'operations.clarifications', 'operations.executions', 'operations.steps']);
        $operations = $conversation->operations->map(fn (AiOperationRequest $operation): array => $this->operation($operation))->values();
        $latest = $conversation->operations->first();

        return [
            'conversation' => $this->conversation($conversation),
            'messages' => $conversation->messages->map(fn (AiMessage $message): array => $this->message($message))->values(),
            'operations' => $operations,
            'latest_operation' => $latest instanceof AiOperationRequest ? $this->operation($latest, true) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function access(Admin $admin, Tenant $tenant): array
    {
        $tenant->loadMissing(['connector', 'operations']);

        return [
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'username' => $admin->username,
                'email' => $admin->email,
                'role' => $admin->isSuperAdmin() ? 'superadmin' : 'admin',
            ],
            'tenant' => $this->tenant($tenant),
            'membership_role' => $this->access->membershipRole($admin, $tenant),
            'permissions' => $this->access->permissionNames($admin, $tenant),
            'operations' => $tenant->operations->map(fn ($operation): array => [
                'name' => $operation->operation_name,
                'description' => $operation->description,
                'enabled' => (bool) $operation->enabled,
                'read_only' => (bool) $operation->read_only,
                'approval_required' => (bool) $operation->approval_required,
                'risk_level' => $operation->risk_level,
            ])->values(),
            'connector' => $this->connector($tenant->connector),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>|null
     */
    private function confidence(?array $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        return [
            'understanding_confidence' => $metadata['understanding_confidence'] ?? null,
            'extraction_confidence' => $metadata['extraction_confidence'] ?? null,
            'calibrated' => (bool) ($metadata['calibrated'] ?? false),
            'calibration_version' => $metadata['calibration_version'] ?? null,
            'method' => $metadata['method'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function connector(?TenantConnector $connector): ?array
    {
        if (! $connector instanceof TenantConnector) {
            return null;
        }

        return [
            'status' => $connector->status,
            'mode' => $connector->mode,
            'version' => $connector->connector_version,
            'last_health_check_at' => $connector->last_health_check_at?->toIso8601String(),
            'last_health_message' => $connector->last_health_message,
        ];
    }

    private function publicUrl(mixed $path): ?string
    {
        if (! is_string($path) || $path === '' || str_starts_with($path, 'enc:')) {
            return null;
        }
        if (str_starts_with($path, 'https://')) {
            return $path;
        }
        if (str_starts_with($path, 'http://')) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    private function color(string $value): string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1 ? $value : '#7C3AED';
    }
}
