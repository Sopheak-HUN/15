<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Tenants\Modules\IAM\Services\TenantOnboardingService;

class TenantController extends Controller
{
    public function store(Request $request, TenantOnboardingService $service)
    {
        $request->validate([
            'name' => 'required|string',
            'handle' => 'required|string|unique:tenants,handle',
        ]);

        $tenant = $service->onboard($request->name, $request->handle);

        return response()->json(['success' => true, 'tenant' => $tenant]);
    }
}
