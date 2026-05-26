<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Geo\Commune;
use App\Models\Geo\District;
use App\Models\Geo\Province;
use App\Models\Geo\Village;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Serves Cambodia's administrative geography (province → district → commune
 * → village) from the local database. Populated by `php artisan geo:import`
 * which pulls from the MEF open data portal.
 *
 * Each sub-level endpoint accepts an optional parent-code filter so the
 * cascading UI fetches only the rows it needs (a typical village list is
 * 5–30 rows instead of 14,576).
 */
final class GeoController extends Controller
{
    private const COLUMNS_BY_LEVEL = [
        'provinces' => ['code', 'name_kh', 'name_en'],
        'districts' => ['code', 'province_code', 'name_kh', 'name_en'],
        'communes'  => ['code', 'district_code', 'name_kh', 'name_en'],
        'villages'  => ['code', 'commune_code', 'name_kh', 'name_en'],
    ];

    private const MODEL_BY_LEVEL = [
        'provinces' => Province::class,
        'districts' => District::class,
        'communes'  => Commune::class,
        'villages'  => Village::class,
    ];

    private const FILTER_KEY_BY_LEVEL = [
        'districts' => 'province_code',
        'communes'  => 'district_code',
        'villages'  => 'commune_code',
    ];

    public function show(string $level, Request $request): JsonResponse
    {
        if (!isset(self::COLUMNS_BY_LEVEL[$level])) {
            return response()->json(['message' => "Unknown level: {$level}"], 404);
        }

        $model = self::MODEL_BY_LEVEL[$level];
        $query = $model::query();

        if (isset(self::FILTER_KEY_BY_LEVEL[$level])) {
            $filterKey = self::FILTER_KEY_BY_LEVEL[$level];
            $parentCode = $request->query($filterKey);
            if ($parentCode !== null && $parentCode !== '') {
                $query->where($filterKey, $parentCode);
            }
        }

        $rows = $query
            ->orderBy('name_en')
            ->get(self::COLUMNS_BY_LEVEL[$level]);

        return response()
            ->json($rows)
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
