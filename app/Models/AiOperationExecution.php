<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiOperationExecution extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'response_data' => 'array',
            'executed_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
}
