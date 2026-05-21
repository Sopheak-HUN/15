<?php

namespace App\Tenants\Modules\HRM\Models;

use App\Tenants\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    use HasUuids, Auditable;

    protected $guarded = [];

    protected $casts = [
        'gross_earnings'   => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_pay'          => 'decimal:2',
        'line_items'       => 'array',
        'issued_at'        => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
