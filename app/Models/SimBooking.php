<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SimBooking extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'arrival' => 'date:Y-m-d',
            'departure' => 'date:Y-m-d',
            'guests' => 'integer',
        ];
    }

    public function nights(): HasMany
    {
        return $this->hasMany(SimReservedNight::class, 'booking_id');
    }

    public function shortReference(): string
    {
        return substr((string) $this->reference, 0, 8);
    }
}
