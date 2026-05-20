<?php

namespace App\Tenants\Modules\IAM\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $guarded = [];

    // Audit Logs shouldn't be auditable to prevent infinite loops.
}
