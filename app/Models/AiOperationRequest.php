<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiOperationRequest extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    public const OPEN_STATUSES = [
        'received',
        'transcribing',
        'analyzing',
        'awaiting_clarification',
        'planning',
        'validating',
        'awaiting_approval',
        'ready',
        'failed',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'missing_fields' => 'array',
            'ambiguities' => 'array',
            'confidence_metadata' => 'array',
            'confidence_score' => 'float',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'initiated_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AiOperationStep::class, 'operation_request_id')->orderBy('step_number');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(AiOperationExecution::class, 'operation_request_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(AiOperationStateHistory::class, 'operation_request_id')->orderBy('created_at');
    }

    public function clarifications(): HasMany
    {
        return $this->hasMany(AiClarification::class, 'operation_request_id')->orderBy('round_number');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(AiApproval::class, 'operation_request_id')->orderByDesc('created_at');
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(AiEscalation::class, 'operation_request_id');
    }
}
