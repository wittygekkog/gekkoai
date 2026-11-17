<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use HasUuids, Notifiable;

    protected $table = 'admins';

    protected $guarded = ['id', 'admin_type', 'status'];

    protected $hidden = ['password', 'remember_token', 'mfa_secret'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'mfa_secret' => 'encrypted',
            'must_change_password' => 'boolean',
            'mfa_enabled' => 'boolean',
            'admin_type' => 'integer',
            'status' => 'integer',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return (int) $this->admin_type === 0;
    }

    public function isActive(): bool
    {
        return (int) $this->status === 1;
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantUser::class, 'user_id');
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_users', 'user_id', 'tenant_id')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }
}
