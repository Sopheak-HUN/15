<?php

namespace App\Tenants\Modules\IAM\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Tenants\Modules\IAM\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::orderBy('created_at', 'desc')->paginate(50);
        return response()->json(['data' => $logs]);
    }
}
