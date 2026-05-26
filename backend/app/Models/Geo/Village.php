<?php

declare(strict_types=1);

namespace App\Models\Geo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Village extends Model
{
    protected $table = 'villages';
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['code', 'commune_code', 'name_kh', 'name_en'];

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class, 'commune_code', 'code');
    }
}
