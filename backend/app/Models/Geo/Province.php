<?php

declare(strict_types=1);

namespace App\Models\Geo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Province extends Model
{
    protected $table = 'provinces';
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['code', 'name_kh', 'name_en'];

    public function districts(): HasMany
    {
        return $this->hasMany(District::class, 'province_code', 'code');
    }
}
