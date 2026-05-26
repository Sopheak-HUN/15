<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\GeoController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

Route::post('/tenants', [TenantController::class, 'store']);

// Cambodia administrative geography (reference data, not tenant-scoped).
// Cached 30 days; pre-warm with `php artisan geo:refresh` after deploy.
Route::get('/geo/{level}', [GeoController::class, 'show'])
    ->where('level', 'provinces|districts|communes|villages');
