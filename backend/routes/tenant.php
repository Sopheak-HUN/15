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
use App\Tenants\Modules\HRM\Controllers\EmployeePromotionController;
use App\Tenants\Modules\HRM\Controllers\EmployeeNoteController;
use App\Tenants\Modules\HRM\Controllers\EmployeeDocumentController;
use App\Tenants\Modules\HRM\Controllers\AttendanceController;
use App\Http\Controllers\UploadController;

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
        // ---------- Uploads (S3-compatible presigned URLs) ----------
        Route::post('/api/uploads/employee-photo',    [UploadController::class, 'employeePhoto']);
        Route::post('/api/uploads/employee-document', [UploadController::class, 'employeeDocument']);
        Route::post('/api/uploads/leave-reference',   [UploadController::class, 'leaveReference']);

        // ---------- IAM ----------
        Route::post('/api/auth/logout', [AuthController::class, 'logout']);
        Route::post('/api/auth/mfa/setup', [AuthController::class, 'setupMfa']);
        Route::post('/api/auth/mfa/verify', [AuthController::class, 'verifyMfa']);

        // Roles: read is required to even see the page; write actions
        // require the verb-specific permission so a "role viewer" account
        // can browse but not mutate.
        Route::middleware('permission:iam.roles.view')->group(function () {
            Route::get('/api/iam/roles', [RoleController::class, 'index']);
            Route::get('/api/iam/roles/{role}', [RoleController::class, 'show']);
        });
        Route::middleware('permission:iam.roles.create')->post('/api/iam/roles', [RoleController::class, 'store']);
        Route::middleware('permission:iam.roles.edit')->match(['put', 'patch'], '/api/iam/roles/{role}', [RoleController::class, 'update']);
        Route::middleware('permission:iam.roles.delete')->delete('/api/iam/roles/{role}', [RoleController::class, 'destroy']);

        Route::middleware('permission:iam.permissions.view')->get('/api/iam/permissions', [PermissionController::class, 'index']);
        Route::middleware('permission:iam.permissions.assign')->post('/api/iam/roles/{role}/permissions', [PermissionController::class, 'sync']);

        // Audit / branding / SSO providers — admin-level concerns. Until
        // dedicated perms exist for these, gate on `iam.roles.view` as a
        // proxy for "this user is an IAM admin." Listed as a follow-up
        // in the seeder TODO.
        Route::middleware('permission:iam.roles.view')->get('/api/iam/audit-logs', [AuditLogController::class, 'index']);
        Route::middleware('permission:iam.roles.edit')->put('/api/iam/branding', [BrandingController::class, 'update']);

        Route::apiResource('/api/iam/sso-providers', SsoController::class)
            ->parameters(['sso-providers' => 'provider'])
            ->only(['store', 'update', 'destroy'])
            ->middleware('permission:iam.roles.edit');

        // Workflow statuses underpin every module's state machine so any
        // authenticated user can read them. Mutations stay admin-gated.
        Route::get('/api/iam/workflow-statuses', [WorkflowStatusController::class, 'index']);
        Route::middleware('permission:iam.roles.edit')->group(function () {
            Route::post('/api/iam/workflow-statuses', [WorkflowStatusController::class, 'store']);
            Route::match(['put', 'patch'], '/api/iam/workflow-statuses/{status}', [WorkflowStatusController::class, 'update']);
            Route::delete('/api/iam/workflow-statuses/{status}', [WorkflowStatusController::class, 'destroy']);
        });

        // ---------- HRM ----------
        // Gating strategy: each HRM submodule is gated by its READ
        // permission for GET routes and its WRITE permission for state-
        // changing routes. Verbs not explicitly listed (PATCH, OPTIONS)
        // fall through to PUT's gate via `match`. Departments / positions
        // / notes / documents share `hrm.employee.*` since they are all
        // employee-adjacent records — we don't want a separate perm just
        // for departments yet.
        Route::prefix('/api/hrm')->group(function () {
            // Self-service: every authenticated user can fetch their own
            // employee record. No permission middleware — the endpoint
            // returns ONLY the caller's row, so there's nothing to gate.
            Route::get('me', [EmployeeController::class, 'me']);

            // Workforce: departments + positions + employees.
            // Employee list/show is also open to anyone who can submit a
            // leave request, because the leave form needs to populate the
            // "assign to" teammate picker. The list endpoint already hides
            // PII fields ($hidden on the model), so what leaks is the
            // company directory — acceptable for an internal ERP.
            Route::middleware('permission:hrm.employee.read,hrm.leave.write')->group(function () {
                Route::get('employees', [EmployeeController::class, 'index']);
                Route::get('employees/{employee}', [EmployeeController::class, 'show']);
            });
            Route::middleware('permission:hrm.employee.read')->group(function () {
                Route::get('departments', [DepartmentController::class, 'index']);
                Route::get('departments/{department}', [DepartmentController::class, 'show']);
                Route::get('positions', [PositionController::class, 'index']);
                Route::get('positions/{position}', [PositionController::class, 'show']);
            });
            Route::middleware('permission:hrm.employee.write')->group(function () {
                Route::apiResource('departments', DepartmentController::class)->only(['store', 'update', 'destroy']);
                Route::apiResource('positions',   PositionController::class)->only(['store', 'update', 'destroy']);
                Route::post('employees', [EmployeeController::class, 'store']);
                Route::match(['put', 'patch'], 'employees/{employee}', [EmployeeController::class, 'update']);
                Route::post('employees/{id}/restore', [EmployeeController::class, 'restore']);
                Route::post('employees/{employee}/user', [EmployeeController::class, 'createUser']);
            });
            Route::middleware('permission:hrm.employee.delete')->delete('employees/{employee}', [EmployeeController::class, 'destroy']);

            // Career journal (promotions / transfers / salary adjustments).
            // Read gated under employee.read; write gated under employee.write.
            Route::middleware('permission:hrm.employee.read')->group(function () {
                Route::get('employees/{employeeId}/promotions', [EmployeePromotionController::class, 'index']);
            });
            Route::middleware('permission:hrm.employee.write')->group(function () {
                Route::post('employees/{employeeId}/promotions', [EmployeePromotionController::class, 'store']);
                Route::delete('employees/{employeeId}/promotions/{promotion}', [EmployeePromotionController::class, 'destroy']);
            });

            // Leave — split into three permission bands so a regular
            // employee (hrm.leave.write = "submit own") can't approve
            // their own request or edit the company-wide leave-type
            // catalog. The controller's index/show methods also auto-
            // scope the result set to the caller's own employee when
            // they lack hrm.employee.read (i.e. they're a staff user).
            Route::middleware('permission:hrm.leave.read')->group(function () {
                Route::get('leave-types', [LeaveTypeController::class, 'index']);
                Route::get('leave-types/{leave_type}', [LeaveTypeController::class, 'show']);
                Route::get('leave-requests', [LeaveRequestController::class, 'index']);
                Route::get('leave-requests/{leave_request}', [LeaveRequestController::class, 'show']);
                Route::get('employees/{employeeId}/leave-balances', [LeaveRequestController::class, 'balances']);
            });
            // Submit own leave
            Route::middleware('permission:hrm.leave.write')->post('leave-requests', [LeaveRequestController::class, 'store']);
            // Approve / reject — manager-only
            Route::middleware('permission:hrm.leave.approve')->group(function () {
                Route::post('leave-requests/{leave_request}/approve', [LeaveRequestController::class, 'approve']);
                Route::post('leave-requests/{leave_request}/reject',  [LeaveRequestController::class, 'reject']);
            });
            // Catalog management — HR-only
            Route::middleware('permission:hrm.leave.manage')->group(function () {
                Route::apiResource('leave-types', LeaveTypeController::class)
                    ->parameters(['leave-types' => 'leave_type'])
                    ->only(['store', 'update', 'destroy']);
            });

            // Payroll
            Route::middleware('permission:hrm.payroll.read')->group(function () {
                Route::get('pay-components', [PayComponentController::class, 'index']);
                Route::get('pay-components/{pay_component}', [PayComponentController::class, 'show']);
                Route::get('payroll-periods', [PayrollPeriodController::class, 'index']);
                Route::get('payroll-periods/{payroll_period}', [PayrollPeriodController::class, 'show']);
                Route::get('payslips', [PayslipController::class, 'index']);
                Route::get('payslips/{payslip}', [PayslipController::class, 'show']);
            });
            Route::middleware('permission:hrm.payroll.write')->group(function () {
                Route::apiResource('pay-components', PayComponentController::class)
                    ->parameters(['pay-components' => 'pay_component'])
                    ->only(['store', 'update', 'destroy']);
                Route::post('payroll-periods', [PayrollPeriodController::class, 'store']);
                Route::post('payroll-periods/{payroll_period}/process', [PayrollPeriodController::class, 'process']);
            });

            // Recruitment
            Route::middleware('permission:hrm.recruitment.read')->group(function () {
                Route::get('vacancies', [VacancyController::class, 'index']);
                Route::get('vacancies/{vacancy}', [VacancyController::class, 'show']);
                Route::get('applications', [ApplicationController::class, 'index']);
                Route::get('applications/{application}', [ApplicationController::class, 'show']);
                Route::get('interviews', [InterviewController::class, 'index']);
            });
            Route::middleware('permission:hrm.recruitment.write')->group(function () {
                Route::apiResource('vacancies', VacancyController::class)->only(['store', 'update', 'destroy']);
                Route::post('applications', [ApplicationController::class, 'store']);
                Route::delete('applications/{application}', [ApplicationController::class, 'destroy']);
                Route::post('applications/{application}/transition',
                    [ApplicationController::class, 'transition']);
                Route::post('applications/{application}/convert-to-employee',
                    [ApplicationController::class, 'convertToEmployee']);
                Route::post('applications/bulk-convert-to-employee',
                    [ApplicationController::class, 'bulkConvert']);
                Route::post('applications/{application}/revert-employee-conversion',
                    [ApplicationController::class, 'revertConversion']);
                Route::apiResource('interviews', InterviewController::class)
                    ->only(['store', 'update', 'destroy']);
                Route::post('interviews/{interview}/feedbacks', [InterviewController::class, 'storeFeedback']);
            });

            // Notes & Documents — employee-scoped records.
            Route::middleware('permission:hrm.employee.read')->group(function () {
                Route::get('employee-notes', [EmployeeNoteController::class, 'index']);
                Route::get('employee-notes/{employee_note}', [EmployeeNoteController::class, 'show']);
                Route::get('employee-documents', [EmployeeDocumentController::class, 'index']);
                Route::get('employee-documents/{employee_document}', [EmployeeDocumentController::class, 'show']);
            });
            Route::middleware('permission:hrm.employee.write')->group(function () {
                Route::apiResource('employee-notes', EmployeeNoteController::class)
                    ->parameters(['employee-notes' => 'employee_note'])
                    ->only(['store', 'update', 'destroy']);
                Route::post('employee-documents', [EmployeeDocumentController::class, 'store']);
                Route::delete('employee-documents/{employee_document}', [EmployeeDocumentController::class, 'destroy']);
            });

            // Attendance
            Route::middleware('permission:hrm.attendance.read')->group(function () {
                Route::get('attendances', [AttendanceController::class, 'index']);
                Route::get('attendances/{attendance}', [AttendanceController::class, 'show']);
                Route::get('attendance/stats', [AttendanceController::class, 'stats']);
            });
            Route::middleware('permission:hrm.attendance.write')->group(function () {
                Route::apiResource('attendances', AttendanceController::class)->only(['store', 'update', 'destroy']);
                Route::post('attendance/check-in',  [AttendanceController::class, 'checkIn']);
                Route::post('attendance/break-out', [AttendanceController::class, 'breakOut']);
                Route::post('attendance/break-in',  [AttendanceController::class, 'breakIn']);
                Route::post('attendance/check-out', [AttendanceController::class, 'checkOut']);
            });
        });
    });
});
