<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;
use App\Tenants\Modules\IAM\Controllers\AuthController;
use App\Tenants\Modules\IAM\Controllers\RoleController;
use App\Tenants\Modules\IAM\Controllers\PermissionController;
use App\Tenants\Modules\IAM\Controllers\AuditLogController;
use App\Tenants\Modules\IAM\Controllers\BrandingController;

// Tenant is identified via the `tenant` HTTP header (or `?tenant=` query param)
// carrying the tenant handle/id. Header name is configured in TenancyServiceProvider.
Route::middleware([
    'api',
    InitializeTenancyByRequestData::class,
])->group(function () {
    Route::post('/api/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function() {
        Route::post('/api/auth/logout', [AuthController::class, 'logout']);
        Route::post('/api/auth/mfa/setup', [AuthController::class, 'setupMfa']);
        Route::post('/api/auth/mfa/verify', [AuthController::class, 'verifyMfa']);

        Route::apiResource('/api/iam/roles', RoleController::class);
        Route::get('/api/iam/permissions', [PermissionController::class, 'index']);
        Route::post('/api/iam/roles/{role}/permissions', [PermissionController::class, 'sync']);

        Route::get('/api/iam/audit-logs', [AuditLogController::class, 'index']);
        Route::put('/api/iam/branding', [BrandingController::class, 'update']);
    });
});
