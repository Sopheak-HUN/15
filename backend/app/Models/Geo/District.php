<?php

declare(strict_types=1);

namespace App\Models\Geo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class District extends Model
{
    protected $table = 'districts';
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['code', 'province_code', 'name_kh', 'name_en'];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_code', 'code');
    }

    public function communes(): HasMany
    {
        return $this->hasMany(Commune::class, 'district_code', 'code');
    }
}
