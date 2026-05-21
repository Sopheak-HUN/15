<?php

namespace App\Tenants\Modules\HRM\Models;

use App\Tenants\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Suggestion extends Model
{
    use HasUuids, SoftDeletes, Auditable;

    protected $guarded = [];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'reviewed_at'  => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewed_by');
    }

    /**
     * Hide the submitter when the record is anonymous. Even an admin
     * reading a row should not be able to derive identity from the JSON.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format(\DateTimeInterface::ATOM);
    }

    public function toArray(): array
    {
        $array = parent::toArray();
        if ($this->is_anonymous) {
            unset($array['employee_id'], $array['employee']);
        }
        return $array;
    }
}
