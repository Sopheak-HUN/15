<?php

namespace App\Http\Controllers;

use App\Services\S3UploadService;
use App\Tenants\Modules\HRM\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function __construct(protected S3UploadService $uploads) {}

    /**
     * Issue a 10-minute presigned PUT URL for an employee photo.
     *
     * The browser uploads the bytes directly to MinIO/S3 — Laravel never
     * touches them. The returned `key` is sent back in the create-employee
     * payload as `photo_temp_key`; EmployeeService::create then moves it
     * under the tenant's permanent prefix.
     *
     * Endpoint: POST /api/uploads/employee-photo
     * Auth: `auth:api` + tenant scope (configured in routes/tenant.php).
     */
    public function employeePhoto(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mime' => 'required|in:image/jpeg,image/png',
            'size' => 'required|integer|min:1|max:2097152', // 2 MB
        ]);

        $payload = $this->uploads->signEmployeePhotoPut($data['mime'], (int) $data['size']);

        return response()->json($payload);
    }

    /**
     * Issue a 10-minute presigned PUT URL for an employee document.
     *
     * Unlike the photo lane, the employee already exists (the dialog
     * opens from the detail page), so the upload writes straight to
     * `tenants/{handle}/employees/{employee_id}/documents/...` — no
     * temp-commit dance.
     *
     * Endpoint: POST /api/uploads/employee-document
     */
    public function employeeDocument(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|uuid|exists:employees,id',
            'mime'        => 'required|string|max:160',
            'size'        => 'required|integer|min:1|max:10485760', // 10 MB
        ]);

        // Resolve the employee within the current tenant context to make
        // sure the upload lane can't be used to write into another
        // tenant's prefix via a forged employee_id.
        Employee::findOrFail($data['employee_id']);

        $payload = $this->uploads->signEmployeeDocumentPut(
            $data['employee_id'],
            $data['mime'],
            (int) $data['size'],
        );

        return response()->json($payload);
    }

    /**
     * Issue a 10-minute presigned PUT URL for a leave-request reference
     * file (medical certificate, travel doc, etc.). Key lands under the
     * requester's per-employee prefix.
     *
     * Endpoint: POST /api/uploads/leave-reference
     */
    public function leaveReference(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|uuid|exists:employees,id',
            'mime'        => 'required|string|max:160',
            'size'        => 'required|integer|min:1|max:10485760',
        ]);

        Employee::findOrFail($data['employee_id']);

        $payload = $this->uploads->signLeaveReferencePut(
            $data['employee_id'],
            $data['mime'],
            (int) $data['size'],
        );

        return response()->json($payload);
    }
}
