<?php

declare(strict_types=1);

namespace App\Models\Geo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Commune extends Model
{
    protected $table = 'communes';
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['code', 'district_code', 'name_kh', 'name_en'];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_code', 'code');
    }

    public function villages(): HasMany
    {
        return $this->hasMany(Village::class, 'commune_code', 'code');
    }
}
