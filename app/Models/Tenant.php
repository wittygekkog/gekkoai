<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    public function connector(): HasOne
    {
        return $this->hasOne(TenantConnector::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(TenantOperation::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
