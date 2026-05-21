<?php

namespace App\Tenants\Modules\IAM\Models;

use App\Tenants\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WorkflowStatus extends Model
{
    use HasUuids, Auditable;

    protected $guarded = [];

    protected $casts = [
        'allowed_transitions' => 'array',
        'is_initial'          => 'boolean',
        'is_terminal'         => 'boolean',
        'sort_order'          => 'integer',
    ];
}
