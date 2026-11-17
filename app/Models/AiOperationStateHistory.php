<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiOperationStateHistory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'ai_operation_state_history';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
