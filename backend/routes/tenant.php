<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;
use App\Tenants\Modules\IAM\Controllers\AuthController;
use App\Tenants\Modules\IAM\Controllers\RoleController;
use App\Tenants\Modules\IAM\Controllers\PermissionController;
use App\Tenants\Modules\IAM\Controllers\AuditLogController;
use App\Tenants\Modules\IAM\Controllers\BrandingController;
use App\Tenants\Modules\IAM\Controllers\SsoController;
use App\Tenants\Modules\IAM\Controllers\WorkflowStatusController;

// HRM
use App\Tenants\Modules\HRM\Controllers\DepartmentController;
use App\Tenants\Modules\HRM\Controllers\PositionController;
use App\Tenants\Modules\HRM\Controllers\EmployeeController;
use App\Tenants\Modules\HRM\Controllers\LeaveTypeController;
use App\Tenants\Modules\HRM\Controllers\LeaveRequestController;
use App\Tenants\Modules\HRM\Controllers\PayComponentController;
use App\Tenants\Modules\HRM\Controllers\PayrollPeriodController;
use App\Tenants\Modules\HRM\Controllers\PayslipController;
use App\Tenants\Modules\HRM\Controllers\VacancyController;
use App\Tenants\Modules\HRM\Controllers\ApplicationController;
use App\Tenants\Modules\HRM\Controllers\InterviewController;
use App\Tenants\Modules\HRM\Controllers\AppraisalCycleController;
use App\Tenants\Modules\HRM\Controllers\AppraisalController;
use App\Tenants\Modules\HRM\Controllers\SuggestionController;
use App\Tenants\Modules\HRM\Controllers\EmployeeNoteController;
use App\Tenants\Modules\HRM\Controllers\EmployeeDocumentController;
use App\Tenants\Modules\HRM\Controllers\AttendanceController;

// Tenant is identified via the `tenant` HTTP header (or `?tenant=` query param)
// carrying the tenant handle/id. Header name is configured in TenancyServiceProvider.
Route::middleware([
    'api',
    InitializeTenancyByRequestData::class,
])->group(function () {
    Route::post('/api/auth/login', [AuthController::class, 'login']);

    // SSO bootstrap (unauthenticated — the IdP authenticates the user).
    Route::get('/api/auth/sso/providers', [SsoController::class, 'index']);
    Route::get('/api/auth/sso/{provider}/redirect', [SsoController::class, 'redirect']);
    Route::post('/api/auth/sso/callback', [SsoController::class, 'callback']);

    Route::middleware('auth:api')->group(function() {
        // ---------- IAM ----------
        Route::post('/api/auth/logout', [AuthController::class, 'logout']);
        Route::post('/api/auth/mfa/setup', [AuthController::class, 'setupMfa']);
        Route::post('/api/auth/mfa/verify', [AuthController::class, 'verifyMfa']);

        Route::apiResource('/api/iam/roles', RoleController::class);
        Route::get('/api/iam/permissions', [PermissionController::class, 'index']);
        Route::post('/api/iam/roles/{role}/permissions', [PermissionController::class, 'sync']);

        Route::get('/api/iam/audit-logs', [AuditLogController::class, 'index']);
        Route::put('/api/iam/branding', [BrandingController::class, 'update']);

        Route::apiResource('/api/iam/sso-providers', SsoController::class)
            ->parameters(['sso-providers' => 'provider'])
            ->only(['store', 'update', 'destroy']);

        // Tenant-scoped workflow statuses (used by HRM, FMS, etc.)
        Route::apiResource('/api/iam/workflow-statuses', WorkflowStatusController::class)
            ->parameters(['workflow-statuses' => 'status'])
            ->only(['index', 'store', 'update', 'destroy']);

        // ---------- HRM ----------
        Route::prefix('/api/hrm')->group(function () {
            // Workforce
            Route::apiResource('departments', DepartmentController::class);
            Route::apiResource('positions',   PositionController::class);
            Route::apiResource('employees',   EmployeeController::class);
            Route::post('employees/{id}/restore', [EmployeeController::class, 'restore']);

            // Leave
            Route::apiResource('leave-types', LeaveTypeController::class)
                ->parameters(['leave-types' => 'leave_type']);
            Route::apiResource('leave-requests', LeaveRequestController::class)
                ->parameters(['leave-requests' => 'leave_request'])
                ->only(['index', 'show', 'store']);
            Route::post('leave-requests/{leave_request}/approve', [LeaveRequestController::class, 'approve']);
            Route::post('leave-requests/{leave_request}/reject',  [LeaveRequestController::class, 'reject']);
            Route::get('employees/{employeeId}/leave-balances',   [LeaveRequestController::class, 'balances']);

            // Payroll
            Route::apiResource('pay-components', PayComponentController::class)
                ->parameters(['pay-components' => 'pay_component']);
            Route::apiResource('payroll-periods', PayrollPeriodController::class)
                ->parameters(['payroll-periods' => 'payroll_period'])
                ->only(['index', 'show', 'store']);
            Route::post('payroll-periods/{payroll_period}/process', [PayrollPeriodController::class, 'process']);
            Route::get('payslips',       [PayslipController::class, 'index']);
            Route::get('payslips/{payslip}', [PayslipController::class, 'show']);

            // Recruitment
            Route::apiResource('vacancies',     VacancyController::class);
            Route::apiResource('applications',  ApplicationController::class)
                ->only(['index', 'show', 'store', 'destroy']);
            Route::post('applications/{application}/transition',
                [ApplicationController::class, 'transition']);
            Route::post('applications/{application}/convert-to-employee',
                [ApplicationController::class, 'convertToEmployee']);
            Route::post('applications/bulk-convert-to-employee',
                [ApplicationController::class, 'bulkConvert']);
            Route::post('applications/{application}/revert-employee-conversion',
                [ApplicationController::class, 'revertConversion']);
            Route::apiResource('interviews', InterviewController::class)
                ->only(['index', 'store', 'update', 'destroy']);
            Route::post('interviews/{interview}/feedbacks', [InterviewController::class, 'storeFeedback']);

            // Performance
            Route::apiResource('appraisal-cycles', AppraisalCycleController::class)
                ->parameters(['appraisal-cycles' => 'appraisal_cycle']);
            Route::apiResource('appraisals', AppraisalController::class)
                ->only(['index', 'show', 'store']);
            Route::post('appraisals/{appraisal}/submit', [AppraisalController::class, 'submit']);
            Route::post('appraisals/{appraisal}/review', [AppraisalController::class, 'review']);
            Route::post('appraisals/{appraisal}/close',  [AppraisalController::class, 'close']);

            // Suggestions
            Route::apiResource('suggestions', SuggestionController::class)
                ->only(['index', 'show', 'store', 'destroy']);
            Route::post('suggestions/{suggestion}/transition', [SuggestionController::class, 'transition']);

            // Notes & Documents
            Route::apiResource('employee-notes', EmployeeNoteController::class)
                ->parameters(['employee-notes' => 'employee_note']);
            Route::apiResource('employee-documents', EmployeeDocumentController::class)
                ->parameters(['employee-documents' => 'employee_document'])
                ->only(['index', 'show', 'store', 'destroy']);

            // Attendance
            Route::apiResource('attendances', AttendanceController::class);
            Route::post('attendance/check-in',  [AttendanceController::class, 'checkIn']);
            Route::post('attendance/check-out', [AttendanceController::class, 'checkOut']);
            Route::get('attendance/stats',      [AttendanceController::class, 'stats']);
        });
    });
});
