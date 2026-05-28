<?php

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\HRM\Models\Employee;
use App\Tenants\Modules\HRM\Services\EmployeeService;
use DomainException;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(protected EmployeeService $service) {}

    /**
     * Return the Employee row linked to the authenticated user (via
     * employees.user_id = users.id). Used by self-service screens like
     * the leave form so a staff member can pre-fill their own employee
     * record without needing the hrm.employee.read permission.
     *
     * Returns 200 with `data: null` rather than 404 when the user has
     * no linked employee — that's an expected state for new admin
     * accounts that haven't been provisioned an employee profile yet.
     */
    public function me(Request $request)
    {
        $userId = $request->user()?->id;
        if (! $userId) {
            return response()->json(['data' => null]);
        }

        // Eager-load department (with description + manager) so the
        // /profile screen can render "your department" info without
        // making a second call that would 403 for staff users.
        $employee = Employee::where('user_id', $userId)
            ->with([
                'department:id,name,description,manager_id',
                'department.manager:id,first_name,last_name,employee_id,email,phone',
                'position:id,title',
                'manager:id,first_name,last_name,employee_id',
                'activeContract',
                // Career journal so the profile page can render a
                // read-only "My career" timeline without a follow-up call.
                'promotions' => fn ($q) => $q->orderByDesc('effective_date')->orderByDesc('created_at'),
                'promotions.previousPosition:id,title',
                'promotions.newPosition:id,title',
                'promotions.previousDepartment:id,name',
                'promotions.newDepartment:id,name',
                'promotions.approver:id,first_name,last_name,employee_id',
            ])
            ->first();

        return response()->json(['data' => $employee]);
    }

    public function index(Request $request)
    {
        $query = Employee::query()
            ->with(['department:id,name', 'position:id,title', 'manager:id,first_name,last_name']);

        if ($request->filled('q')) {
            $term = '%' . $request->string('q') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'ilike', $term)
                  ->orWhere('last_name', 'ilike', $term)
                  ->orWhere('email', 'ilike', $term)
                  ->orWhere('employee_id', 'ilike', $term);
            });
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->string('department_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json([
            'data' => $query->orderBy('first_name')->paginate($request->integer('per_page', 25)),
        ]);
    }

    public function show(Employee $employee)
    {
        // PII columns (id_card_number, national_id, bank_account, tax_id)
        // are in the model's $hidden array so they don't leak through the
        // index/list endpoint. On the detail endpoint the authenticated
        // user is editing this specific employee, so we unhide them here
        // for the edit form to pre-populate.
        // TODO: gate this on a per-permission policy (hrm.employee.pii_read)
        // once policies are wired across the HRM routes.
        $employee->makeVisible(['national_id', 'id_card_number', 'bank_account', 'tax_id']);

        return response()->json([
            'data' => $employee->load([
                'department', 'position',
                'manager:id,first_name,last_name', 'user:id,email,handle',
                'currentAddress', 'permanentAddress', 'emergencyAddress',
                'spouse', 'emergencyContact', 'educations', 'activeContract', 'contracts',
                // Promotions ordered newest-first so the detail page's
                // Career tab can render the timeline directly from this
                // payload without a follow-up fetch.
                'promotions' => fn ($q) => $q->orderByDesc('effective_date')->orderByDesc('created_at'),
                'promotions.previousPosition:id,title',
                'promotions.newPosition:id,title',
                'promotions.previousDepartment:id,name',
                'promotions.newDepartment:id,name',
                'promotions.approver:id,first_name,last_name,employee_id',
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $employee = $this->service->create($data);
        return response()->json(['success' => true, 'data' => $employee], 201);
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate($this->rules($employee));
        try {
            $employee = $this->service->update($employee, $data);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'data' => $employee]);
    }

    public function destroy(Request $request, Employee $employee)
    {
        try {
            $this->service->terminateEmployee($employee, $request->input('reason'), $request->input('effective_at'));
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true]);
    }

    public function restore(string $id)
    {
        $employee = Employee::withTrashed()->findOrFail($id);
        try {
            $restored = $this->service->restoreEmployee($employee);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'data' => $restored]);
    }

    /**
     * Provision a tenant user account for this employee and link it via
     * `employees.user_id`. The frontend wizard collects the email, an
     * (optionally auto-generated) password, and the role to assign.
     */
    public function createUser(Request $request, Employee $employee)
    {
        $data = $request->validate([
            // Email must be unique among non-trashed users — soft-deleted
            // accounts can keep the same address, matching the partial
            // unique index we use elsewhere.
            'email'    => 'required|email|max:160|unique:users,email,NULL,id,deleted_at,NULL',
            'password' => 'required|string|min:8|max:128',
            'role_id'  => 'required|uuid|exists:roles,id',
            'handle'   => 'nullable|string|max:120|unique:users,handle,NULL,id,deleted_at,NULL',
        ]);

        try {
            $user = $this->service->createUserAccount($employee, $data);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'user' => [
                    'id'        => $user->id,
                    'name'      => $user->name,
                    'email'     => $user->email,
                    'handle'    => $user->handle,
                    'role_id'   => $user->role_id,
                    'is_active' => $user->is_active,
                ],
                'employee' => $employee->fresh()?->load('user:id,email,handle'),
            ],
        ], 201);
    }

    private function rules(?Employee $employee = null): array
    {
        $emailRule = 'required|email|max:160';
        if ($employee) {
            $emailRule .= '|unique:employees,email,' . $employee->id . ',id,deleted_at,NULL';
        } else {
            $emailRule .= '|unique:employees,email,NULL,id,deleted_at,NULL';
        }

        return [
            // Optional on both create and update — auto-generated by EmployeeService
            // when omitted on create (recruitment-style `TT-NNNN` sequence).
            'employee_id'      => 'sometimes|nullable|string|max:32|unique:employees,employee_id' . ($employee ? ',' . $employee->id : ''),
            'first_name'       => ($employee ? 'sometimes' : 'required') . '|string|max:80',
            'last_name'        => ($employee ? 'sometimes' : 'required') . '|string|max:80',
            'first_name_kh'    => 'nullable|string|max:120',
            'last_name_kh'     => 'nullable|string|max:120',
            'email'            => $employee ? str_replace('required', 'sometimes', $emailRule) : $emailRule,
            'phone'            => 'nullable|string|max:32',
            'office_phone'     => 'nullable|string|max:32',
            'contact_phone'    => 'nullable|string|max:32',
            'date_of_birth'    => 'nullable|date|before:today',
            'gender'           => 'nullable|in:male,female,other,prefer_not_to_say',
            'nationality'      => 'nullable|string|max:64',
            'national_id'      => 'nullable|string|max:64',
            'bank_account'     => 'nullable|string|max:64',
            'tax_id'           => 'nullable|string|max:64',
            'nssf_id'          => 'nullable|string|max:64',
            'role_name'        => 'nullable|string|max:120',

            // Identification document (the wizard's "Type of ID" block).
            'identification_type' => 'nullable|in:national_id,passport,drivers_license,family_book,other',
            'id_card_number'      => 'nullable|string|max:64',
            'id_issued_date'      => 'nullable|date',
            'id_issued_by'        => 'nullable|string|max:160',
            'id_issued_place'     => 'nullable|string|max:160',

            // Personal
            'religion'         => 'nullable|in:buddhism,christianity,islam,hinduism,other',
            'marital_status'   => 'nullable|in:single,married,divorced,widowed,separated',
            'blood_group'      => 'nullable|string|max:8',
            'children_count'   => 'nullable|integer|min:0|max:32',

            // Legacy flat address (still accepted for callers that don't use the cascade)
            'address'          => 'nullable|string|max:255',
            'city'             => 'nullable|string|max:120',
            'country'          => 'nullable|string|max:64',

            // Object-storage key returned by POST /api/uploads/employee-photo.
            // Must live under the `uploads/` prefix — EmployeeService refuses
            // any other prefix to prevent forged-key copy-from abuse.
            'photo_temp_key'   => 'nullable|string|max:255|starts_with:uploads/',

            'department_id'    => 'nullable|uuid|exists:departments,id',
            'position_id'      => 'nullable|uuid|exists:positions,id',
            'manager_id'       => 'nullable|uuid|exists:employees,id',
            'user_id'          => 'nullable|uuid|exists:users,id',
            'hire_date'        => 'nullable|date',
            'employment_type'  => 'nullable|in:full_time,part_time,contract,intern',
            'status'           => 'nullable|string|max:32',
            'base_salary'      => 'nullable|numeric|min:0',
            'currency'         => 'nullable|string|size:3',
            'pay_frequency'    => 'nullable|in:weekly,biweekly,monthly',

            // Nested wizard sub-payloads. Each block is optional; the service
            // skips creating a row when the block is missing or all-empty.
            'current_address'                  => 'nullable|array',
            'current_address.home_number'      => 'nullable|string|max:64',
            'current_address.street'           => 'nullable|string|max:255',
            'current_address.province_code'    => 'nullable|string|max:16',
            'current_address.district_code'    => 'nullable|string|max:16',
            'current_address.commune_code'     => 'nullable|string|max:16',
            'current_address.village_code'     => 'nullable|string|max:16',
            'current_address.group'            => 'nullable|string|max:32',
            'current_address.lat'              => 'nullable|numeric|between:-90,90',
            'current_address.lng'              => 'nullable|numeric|between:-180,180',

            'permanent_address'                => 'nullable|array',
            'permanent_address.home_number'    => 'nullable|string|max:64',
            'permanent_address.street'         => 'nullable|string|max:255',
            'permanent_address.province_code'  => 'nullable|string|max:16',
            'permanent_address.district_code'  => 'nullable|string|max:16',
            'permanent_address.commune_code'   => 'nullable|string|max:16',
            'permanent_address.village_code'   => 'nullable|string|max:16',
            'permanent_address.group'          => 'nullable|string|max:32',
            'permanent_address.lat'            => 'nullable|numeric|between:-90,90',
            'permanent_address.lng'            => 'nullable|numeric|between:-180,180',

            'emergency_address'                => 'nullable|array',
            'emergency_address.home_number'    => 'nullable|string|max:64',
            'emergency_address.street'         => 'nullable|string|max:255',
            'emergency_address.province_code'  => 'nullable|string|max:16',
            'emergency_address.district_code'  => 'nullable|string|max:16',
            'emergency_address.commune_code'   => 'nullable|string|max:16',
            'emergency_address.village_code'   => 'nullable|string|max:16',
            'emergency_address.group'          => 'nullable|string|max:32',
            'emergency_address.lat'            => 'nullable|numeric|between:-90,90',
            'emergency_address.lng'            => 'nullable|numeric|between:-180,180',

            'spouse'                  => 'nullable|array',
            'spouse.name'             => 'nullable|string|max:160',
            'spouse.date_of_birth'    => 'nullable|date',
            'spouse.education'        => 'nullable|string|max:32',
            'spouse.occupation'       => 'nullable|string|max:160',

            'emergency_contact'                    => 'nullable|array',
            'emergency_contact.father_name'        => 'nullable|string|max:160',
            'emergency_contact.father_occupation'  => 'nullable|string|max:160',
            'emergency_contact.mother_name'        => 'nullable|string|max:160',
            'emergency_contact.mother_occupation'  => 'nullable|string|max:160',
            'emergency_contact.phone_number'       => 'nullable|string|max:32',
            'emergency_contact.home_phone'         => 'nullable|string|max:32',

            'educations'                       => 'nullable|array',
            'educations.*.level'               => 'nullable|string|max:32',
            'educations.*.major_subject'       => 'nullable|string|max:160',
            'educations.*.status'              => 'nullable|string|max:32',
            'educations.*.university_school'   => 'nullable|string|max:200',

            'contract'              => 'nullable|array',
            'contract.type'         => 'nullable|in:work,fdc,udc,probation,internship,consulting',
            'contract.start_date'   => 'nullable|date',
            'contract.end_date'     => 'nullable|date|after_or_equal:contract.start_date',
            'contract.comment'      => 'nullable|string|max:2000',
        ];
    }
}
