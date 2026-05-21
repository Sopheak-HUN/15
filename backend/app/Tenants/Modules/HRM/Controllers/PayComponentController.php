<?php

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\HRM\Models\PayComponent;
use Illuminate\Http\Request;

class PayComponentController extends Controller
{
    public function index()
    {
        return response()->json(['data' => PayComponent::orderBy('kind')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        return response()->json(['success' => true, 'data' => PayComponent::create($data)], 201);
    }

    public function update(Request $request, PayComponent $pay_component)
    {
        $data = $request->validate($this->rules($pay_component));
        $pay_component->update($data);
        return response()->json(['success' => true, 'data' => $pay_component->refresh()]);
    }

    public function destroy(PayComponent $pay_component)
    {
        $pay_component->delete();
        return response()->json(['success' => true]);
    }

    private function rules(?PayComponent $c = null): array
    {
        return [
            'name'        => 'required|string|max:120',
            'code'        => 'required|string|max:32|unique:pay_components,code' . ($c ? ',' . $c->id : ''),
            'kind'        => 'required|in:earning,deduction',
            'calculation' => 'required|in:fixed,percentage_of_base',
            'amount'      => 'required|numeric',
            'is_taxable'  => 'boolean',
            'is_active'   => 'boolean',
        ];
    }
}
