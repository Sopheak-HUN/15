<?php

namespace App\Tenants\Modules\IAM\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BrandingController extends Controller
{
    public function update(Request $request)
    {
        $request->validate([
            'logo_path' => 'nullable|string',
            'primary_color' => 'nullable|string',
            'secondary_color' => 'nullable|string',
        ]);

        $tenant = tenant();
        $tenant->update($request->only('logo_path', 'primary_color', 'secondary_color'));

        return response()->json(['success' => true, 'data' => $tenant]);
    }
}
