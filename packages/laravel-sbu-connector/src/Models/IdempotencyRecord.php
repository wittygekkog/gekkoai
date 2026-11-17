<?php

declare(strict_types=1);

namespace WittyGekko\SbuConnector\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class IdempotencyRecord extends Model
{
    use HasUuids;

    protected $table = 'sbu_idempotency_keys';

    protected $fillable = [
        'tenant_id',
        'idempotency_key',
        'operation_name',
        'request_fingerprint',
        'status',
        'operation_id',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
        ];
    }
}
