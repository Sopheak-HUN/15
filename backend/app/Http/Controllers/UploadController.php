<?php

namespace App\Http\Controllers;

use App\Services\S3UploadService;
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
}
