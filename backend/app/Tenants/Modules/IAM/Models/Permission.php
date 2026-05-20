<?php

namespace App\Tenants\Modules\IAM\Models;

use App\Tenants\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasUuids, Auditable;

    protected $guarded = [];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }
}
