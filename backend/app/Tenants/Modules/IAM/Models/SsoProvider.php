<?php

namespace App\Tenants\Modules\IAM\Models;

use App\Tenants\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SsoProvider extends Model
{
    use HasUuids, SoftDeletes, Auditable;

    protected $guarded = [];

    protected $casts = [
        'scopes'        => 'array',
        'metadata'      => 'array',
        'is_active'     => 'boolean',
        // client_secret is sensitive — encrypt at rest. Decrypted in-memory when accessed.
        'client_secret' => 'encrypted',
    ];

    protected $hidden = ['client_secret'];
}
