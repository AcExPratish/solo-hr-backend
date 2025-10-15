<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeFormType;
use App\Enums\EmployeeFormTypeEnum;
use App\Models\Employee;
use App\Models\User;
use App\Traits\FileUploadTrait;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    use FileUploadTrait;

    public function index(): JsonResponse
    {
        try {
            $page   = (int) request('page', 1);
            $limit  = (int) request('limit', 10);
            $search = trim((string) request('search', ''));

            $query = Employee::query()
                ->where("deleted_at", null);

            if (!empty($search)) {
                $userIds = User::query()
                    ->where(function ($q) use ($search) {
                        $q->where('first_name', 'ILIKE', "%{$search}%")
                            ->orWhere('middle_name', 'ILIKE', "%{$search}%")
                            ->orWhere('last_name', 'ILIKE', "%{$search}%")
                            ->orWhere('email', 'ILIKE', "%{$search}%");
                    })
                    ->pluck('id')
                    ->toArray();

                if (!empty($userIds)) {
                    $query->whereIn('user_id', $userIds);
                } else {
                    return $this->sendPaginateResponse(
                        'Fetch all employees',
                        $page,
                        $limit,
                        0,
                        0

                    );
                }
            }

            $total = (clone $query)->count();
            $rows = $query
                ->orderBy('_id', 'desc')
                ->skip(($page - 1) * $limit)
                ->take($limit)
                ->get();

            $rows = $this->attachUserData($rows);

            return $this->sendPaginateResponse(
                'Fetch all employees',
                $page,
                $limit,
                $total,
                $rows

            );
        } catch (\Throwable $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), $this->basicInformationRules());
            if ($validator->fails()) {
                return $this->sendValidationErrors($validator);
            }

            $user = $this->storeOrUpdateUser($request);
            $employee = new Employee();
            $employee->user_id = $user->id;
            $employee->created_by_id = Auth::id();
            $employee->updated_by_id = Auth::id();
            $employee->deleted_at = null;
            $this->storeOrUpdateBasicInformation($request, $employee);
            $employee->save();

            $data = collect($employee)->merge($user);

            DB::commit();
            return $this->sendSuccessResponse("Employee created successfully", $data);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $employee = Employee::find($id);
            if (!$employee) {
                return $this->sendErrorOfNotFound404('Employee not found');
            }

            $row = $this->attachUserData($employee);

            return $this->sendSuccessResponse('Fetch one employee', $row);
        } catch (\Throwable $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function update(Request $request, string $id, string $slug): JsonResponse
    {
        try {
            DB::beginTransaction();

            $employee = Employee::find($id);
            if (!$employee) {
                return $this->sendErrorOfNotFound404("Employee not found");
            }

            switch ($slug) {
                case EmployeeFormTypeEnum::BasicInformation->value:
                    $validator = Validator::make($request->all(), $this->basicInformationRules($employee->user_id));
                    if ($validator->fails()) {
                        return $this->sendValidationErrors($validator);
                    }

                    $this->storeOrUpdateUser($request);
                    $this->storeOrUpdateBasicInformation($request, $employee);
                    break;

                case EmployeeFormTypeEnum::PersonalInformation->value:
                    $validator = Validator::make($request->all(), $this->personalInformationRules($employee->user_id));
                    if ($validator->fails()) {
                        return $this->sendValidationErrors($validator);
                    }

                    $this->storeOrUpdateBasicInformation($request, $employee);
                    break;

                case EmployeeFormTypeEnum::EmergencyContact->value:
                    $validator = Validator::make($request->all(), $this->emergencyContactRules($employee->user_id));
                    if ($validator->fails()) {
                        return $this->sendValidationErrors($validator);
                    }

                    $this->storeOrUpdateEmergencyContact($request, $employee);
                    break;

                case EmployeeFormTypeEnum::About->value:
                    $validator = Validator::make($request->all(), $this->aboutRules($employee->user_id));
                    if ($validator->fails()) {
                        return $this->sendValidationErrors($validator);
                    }

                    $this->storeOrUpdateBasicInformation($request, $employee);
                    break;

                case EmployeeFormTypeEnum::BankInformation->value:
                    $validator = Validator::make($request->all(), $this->bankInformationRules($employee->user_id));
                    if ($validator->fails()) {
                        return $this->sendValidationErrors($validator);
                    }

                    $this->storeOrUpdateBankInformation($request, $employee);
                    break;

                case EmployeeFormTypeEnum::FamilyInformation->value:
                    $validator = Validator::make($request->all(), $this->familyInformationRules($employee->user_id));
                    if ($validator->fails()) {
                        return $this->sendValidationErrors($validator);
                    }

                    $this->storeOrUpdateFamilyInformation($request, $employee);
                    break;

                case EmployeeFormTypeEnum::StatutoryInformation->value:
                    $this->storeOrUpdateStatutoryInformation($request, $employee);
                    break;

                case EmployeeFormTypeEnum::SupportingDocuments->value:
                    $this->storeOrUpdateSupportingDocuments($request, $employee);
                    break;

                case EmployeeFormTypeEnum::Education->value:
                    $validator = Validator::make($request->all(), $this->educationRules($employee->user_id));
                    if ($validator->fails()) {
                        return $this->sendValidationErrors($validator);
                    }

                    $this->storeOrUpdateEducation($request, $employee);
                    break;

                case EmployeeFormTypeEnum::Experience->value:
                    $validator = Validator::make($request->all(), $this->experienceRules($employee->user_id));
                    if ($validator->fails()) {
                        return $this->sendValidationErrors($validator);
                    }

                    $this->storeOrUpdateExperience($request, $employee);
                    break;

                default:
                    break;
            }

            $employee->save();

            $data = $this->attachUserData($employee);

            DB::commit();
            return $this->sendSuccessResponse("Employee updated successfully", $data);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $employee = Employee::find($id);
            if (!$employee) {
                return $this->sendErrorOfNotFound404('Employee not found');
            }

            if ($employee->user_id) {
                $user = User::find($employee->user_id);
                if ($user) {
                    $user->delete();
                }
            }

            $employee->deleted_at = Carbon::now();
            $employee->save();

            $row = $this->attachUserData($employee);

            DB::commit();
            return $this->sendSuccessResponse('Employee deleted successfully', $row);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    private function attachUserData($rows): Collection|Employee
    {
        if ($rows instanceof Employee) {
            $collection = collect([$rows]);
            $mapped = $this->augmentEmployeesCollection($collection);
            return $mapped->first();
        }

        if ($rows instanceof Collection) {
            return $this->augmentEmployeesCollection($rows);
        }

        throw new \InvalidArgumentException('attachUserData expects Employee model or Collection.');
    }

    private function augmentEmployeesCollection(Collection $rows): Collection
    {
        $userIds = $rows->pluck('user_id')->filter()->unique()->values()->all();

        $users = User::whereIn('id', $userIds)
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'email', 'phone', 'avatar'])
            ->keyBy('id');

        return $rows->map(function ($row) use ($users) {
            $user = $users->get($row->user_id);
            if ($user) {
                $row->setAttribute('first_name', $user->first_name);
                $row->setAttribute('middle_name', $user->middle_name);
                $row->setAttribute('last_name', $user->last_name);
                $row->setAttribute('email', $user->email);
                $row->setAttribute('phone', $user->phone);
                $row->setAttribute('avatar', $user->avatar);
            }
            return $row;
        });
    }

    private function storeOrUpdateUser(Request $request): User
    {
        $user = User::find($request->user_id);
        if (!$user) {
            $user = new User();
            $user->first_name  = $request->first_name;
            $user->middle_name = $request->middle_name;
            $user->last_name = $request->last_name;
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->password = Str::password(12);
            $user->avatar = $request->avatar;
            $user->created_by_id = Auth::id();
            $user->updated_by_id = Auth::id();
            $user->save();
            return $user;
        } else {
            $hasPassword = $request->has('password') && !empty($request->input('password'));
            $user->update([
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'avatar' => $request->avatar,
                'password' => $hasPassword ? Hash::make($request->password) : $user->password,
                'updated_by_id' => Auth::id()
            ]);
            return $user;
        }
    }

    private function storeOrUpdateBasicInformation(Request $request, Employee $employee): void
    {
        $current = (array) ($employee->basic_information ?? null);
        $incoming = (array) $request->input('basic_information', null);
        $allowed = [
            'date_of_birth',
            'gender',
            'nationality',
            'religion',
            'marital_status',
            'employment_of_spouse',
            'no_of_children',
            'blood_group',
            'joining_date',
            'department_id',
            'designation_id',
            'province',
            'district',
            'city',
            'address',
            'zip_code',
            'postal_code',
            'about',
        ];

        $incoming = array_intersect_key($incoming, array_flip($allowed));
        foreach ($incoming as $k => $v) {
            if ($v === '' || (is_array($v) && $v === [])) {
                $incoming[$k] = null;
            }
        }

        $merged = array_replace($current, $incoming);

        $employee->basic_information = $merged;
    }

    private function storeOrUpdateEmergencyContact(Request $request, Employee $employee): void
    {
        $incoming = $request->input('emergency_contact', null);
        if ($incoming === null) {
            return;
        }

        $rows = is_array($incoming) ? array_values($incoming) : [];
        $allowed = ['name', 'relationship', 'phone_1', 'phone_2'];
        $clean = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $filtered = array_intersect_key($row, array_flip($allowed));
            foreach ($filtered as $k => $v) {
                if ($v === '' || (is_array($v) && $v === [])) {
                    $filtered[$k] = null;
                } elseif (in_array($k, ['phone_1', 'phone_2'], true) && is_string($v)) {
                    $v = preg_replace('/[^\d+]/', '', $v);
                    $filtered[$k] = $v !== '' ? $v : null;
                } elseif (is_string($v)) {
                    $filtered[$k] = trim($v);
                }
            }

            $filtered += ['name' => null, 'relationship' => null, 'phone_1' => null, 'phone_2' => null];
            if ($filtered['name'] === null && $filtered['phone_1'] === null && $filtered['phone_2'] === null) {
                continue;
            }

            $clean[] = $filtered;
        }

        $employee->emergency_contact = $clean;
    }

    private function storeOrUpdateBankInformation(Request $request, Employee $employee): void
    {
        $current = (array) ($employee->bank_information ?? null);
        $incoming = (array) $request->input('bank_information', null);
        $allowed = [
            'account_holder_name',
            'account_number',
            'account_type',
            'bank_name',
            'branch_address',
            'swift_code'
        ];

        $incoming = array_intersect_key($incoming, array_flip($allowed));
        foreach ($incoming as $k => $v) {
            if ($v === '' || (is_array($v) && $v === [])) {
                $incoming[$k] = null;
            }
        }

        $merged = array_replace($current, $incoming);

        $employee->bank_information = $merged;
    }

    private function storeOrUpdateFamilyInformation(Request $request, Employee $employee): void
    {
        $incoming = $request->input('family_information', null);
        if ($incoming === null) {
            return;
        }

        $rows = is_array($incoming) ? array_values($incoming) : [];
        $allowed = ['name', 'relationship', 'phone_1', 'phone_2'];
        $clean = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $filtered = array_intersect_key($row, array_flip($allowed));
            foreach ($filtered as $k => $v) {
                if ($v === '' || (is_array($v) && $v === [])) {
                    $filtered[$k] = null;
                } elseif (in_array($k, ['phone_1', 'phone_2'], true) && is_string($v)) {
                    $v = preg_replace('/[^\d+]/', '', $v);
                    $filtered[$k] = $v !== '' ? $v : null;
                } elseif (is_string($v)) {
                    $filtered[$k] = trim($v);
                }
            }

            $filtered += ['name' => null, 'relationship' => null, 'phone_1' => null, 'phone_2' => null];
            if ($filtered['name'] === null && $filtered['phone_1'] === null && $filtered['phone_2'] === null) {
                continue;
            }

            $clean[] = $filtered;
        }

        $employee->family_information = $clean;
    }

    private function storeOrUpdateStatutoryInformation(Request $request, Employee $employee): void
    {
        $incoming = $request->input('statutory_information', null);
        if ($incoming === null) {
            return;
        }

        if (!is_array($incoming)) {
            return;
        }

        $docTypes  = ['citizen_investment_trust', 'health_insurance', 'police_clearance', 'provident_fund', 'social_security_fund', 'tax_clearance'];
        $allowed   = ['id_number', 'issue_date', 'expiry_date', 'issuing_authority', 'image', 'verification_status'];
        $clean = [];

        foreach ($docTypes as $docType) {
            $block = $incoming[$docType] ?? null;
            if (!is_array($block)) {
                continue;
            }

            $filtered = array_intersect_key($block, array_flip($allowed));
            foreach ($filtered as $k => $v) {
                if ($v === '' || $v === [] || $v === null) {
                    $filtered[$k] = null;
                    continue;
                }

                if (is_string($v)) {
                    $filtered[$k] = trim($v);
                }
            }

            $filtered += [
                'id_number' => null,
                'issue_date' => null,
                'expiry_date' => null,
                'issuing_authority' => null,
                'image' => null,
                'verification_status' => null,
            ];

            if ($filtered['verification_status'] === null) {
                $filtered['verification_status'] = 'pending';
            }

            if (
                $filtered['id_number'] === null &&
                $filtered['issue_date'] === null &&
                $filtered['expiry_date'] === null &&
                $filtered['issuing_authority'] === null &&
                $filtered['image'] === null
            ) {
                continue;
            }

            $clean[$docType] = $filtered;
        }

        $employee->statutory_information = $clean;
    }

    private function storeOrUpdateSupportingDocuments(Request $request, Employee $employee): void
    {
        $incoming = $request->input('supporting_documents', null);
        if ($incoming === null) {
            return;
        }

        if (!is_array($incoming)) {
            return;
        }

        $docTypes  = ['pan', 'national_id', 'citizenship', 'passport', 'driving_license'];
        $allowed   = ['id_number', 'issue_date', 'expiry_date', 'issuing_authority', 'image', 'verification_status'];
        $clean = [];

        foreach ($docTypes as $docType) {
            $block = $incoming[$docType] ?? null;
            if (!is_array($block)) {
                continue;
            }

            $filtered = array_intersect_key($block, array_flip($allowed));
            foreach ($filtered as $k => $v) {
                if ($v === '' || $v === [] || $v === null) {
                    $filtered[$k] = null;
                    continue;
                }

                if (is_string($v)) {
                    $filtered[$k] = trim($v);
                }
            }

            $filtered += [
                'id_number' => null,
                'issue_date' => null,
                'expiry_date' => null,
                'issuing_authority' => null,
                'image' => null,
                'verification_status' => null,
            ];

            if ($filtered['verification_status'] === null) {
                $filtered['verification_status'] = 'pending';
            }

            if (
                $filtered['id_number'] === null &&
                $filtered['issue_date'] === null &&
                $filtered['expiry_date'] === null &&
                $filtered['issuing_authority'] === null &&
                $filtered['image'] === null
            ) {
                continue;
            }

            $clean[$docType] = $filtered;
        }

        $employee->supporting_documents = $clean;
    }

    private function storeOrUpdateEducation(Request $request, Employee $employee): void
    {
        $incoming = $request->input('education', null);
        if ($incoming === null) {
            return;
        }

        $rows = is_array($incoming) ? array_values($incoming) : [];
        $allowed = [
            'institution_name',
            'course',
            'start_date',
            'end_date',
            'percentage_or_gpa',
            'is_current'
        ];

        $clean = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $filtered = array_intersect_key($row, array_flip($allowed));
            foreach ($filtered as $k => $v) {
                if ($v === '' || $v === [] || $v === null) {
                    $filtered[$k] = null;
                } elseif (is_string($v)) {
                    $filtered[$k] = trim($v);
                }
            }

            $filtered += [
                'institution_name' => null,
                'course' => null,
                'start_date' => null,
                'end_date' => null,
                'percentage_or_gpa' => null,
                'is_current' => null,
            ];

            if (
                $filtered['institution_name'] === null &&
                $filtered['course'] === null &&
                $filtered['start_date'] === null &&
                $filtered['end_date'] === null &&
                $filtered['percentage_or_gpa'] === null &&
                $filtered['is_current'] === null
            ) {
                continue;
            }

            $filtered['is_current'] = filter_var($filtered['is_current'], FILTER_VALIDATE_BOOLEAN);

            $clean[] = $filtered;
        }

        $employee->education = $clean;
    }

    private function storeOrUpdateExperience(Request $request, Employee $employee): void
    {
        $incoming = $request->input('experience', null);
        if ($incoming === null) {
            return;
        }

        $rows = is_array($incoming) ? array_values($incoming) : [];
        $allowed = [
            'company_name',
            'designation',
            'start_date',
            'end_date',
            'is_current',
        ];

        $clean = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $filtered = array_intersect_key($row, array_flip($allowed));
            foreach ($filtered as $k => $v) {
                if ($v === '' || $v === [] || $v === null) {
                    $filtered[$k] = null;
                } elseif (is_string($v)) {
                    $filtered[$k] = trim($v);
                }
            }

            $filtered += [
                'company_name' => null,
                'designation' => null,
                'start_date' => null,
                'end_date' => null,
                'is_current' => null,
            ];

            if (
                $filtered['company_name'] === null &&
                $filtered['designation'] === null &&
                $filtered['start_date'] === null &&
                $filtered['end_date'] === null &&
                $filtered['is_current'] === null
            ) {
                continue;
            }

            $filtered['is_current'] = filter_var($filtered['is_current'], FILTER_VALIDATE_BOOLEAN);

            $clean[] = $filtered;
        }

        $employee->experience = $clean;
    }

    private function basicInformationRules($id = null): array
    {
        if ($id) {
            return [
                '_id' => ["required", "string"],
                'user_id' => ['required', 'uuid', 'exists:users,id'],
                'first_name'  => ['required', 'string', 'max:100'],
                'middle_name' => ['nullable', 'nullable', 'string', 'max:100'],
                'last_name'   => ['required', 'string', 'max:100'],
                'phone'       => ['required', 'nullable', 'string', 'max:10'],
                'avatar'      => ['sometimes', 'nullable', 'string', 'max:255'],
                'email'       => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
                'password'    => ['sometimes', 'nullable', 'string', 'min:8'],
                'basic_information' => "required|array",
                "basic_information.gender" => "nullable|numeric",
                "basic_information.date_of_birth" => "required|date|before_or_equal:today",
                "basic_information.joining_date" => "required|date",
                "basic_information.province" => "nullable|string|max:100",
                "basic_information.district" => "nullable|string|max:100",
                "basic_information.city" => "nullable|string|max:100",
                "basic_information.address" => "nullable|string|max:255",
                "basic_information.zip_code" => "required|string|max:100",
                "basic_information.postal_code" => "required|string|max:100",
            ];
        } else {
            return [
                'first_name'  => ['required', 'string', 'max:100'],
                'middle_name' => ['nullable', 'string', 'max:100'],
                'last_name'   => ['required', 'string', 'max:100'],
                'phone'       => ['required', 'string', 'max:50'],
                'avatar'      => ['sometimes', 'nullable', 'string', 'max:255'],
                'email'       => ['required', 'email', 'max:255', 'unique:users,email'],
                'password'    => ['required', 'string', 'min:8'],
                'basic_information' => "required|array",
                "basic_information.gender" => "nullable|numeric",
                "basic_information.date_of_birth" => "required|date|before_or_equal:today",
                "basic_information.joining_date" => "required|date",
                "basic_information.province" => "nullable|string|max:100",
                "basic_information.district" => "nullable|string|max:100",
                "basic_information.city" => "nullable|string|max:100",
                "basic_information.address" => "nullable|string|max:255",
                "basic_information.zip_code" => "required|string|max:100",
                "basic_information.postal_code" => "required|string|max:100",
            ];
        }
    }

    private function personalInformationRules($id = null): array
    {
        if ($id) {
            return [
                '_id' => ["required", "string"],
                'user_id' => ['required', 'uuid', 'exists:users,id'],
                'basic_information' => "required|array",
                "basic_information.nationality" => "nullable|string|max:50",
                "basic_information.religion" => "nullable|string|max:50",
                "basic_information.blood_group" => "nullable|string|max:4",
                "basic_information.marital_status" => "nullable|numeric",
                "basic_information.employment_of_spouse" => "nullable|string|max:10",
                "basic_information.no_of_children" => "nullable|numeric|max:10"
            ];
        } else {
            return [
                'basic_information' => "required|array",
                "basic_information.nationality" => "nullable|string|max:50",
                "basic_information.religion" => "nullable|string|max:50",
                "basic_information.blood_group" => "nullable|string|max:4",
                "basic_information.marital_status" => "nullable|numeric",
                "basic_information.employment_of_spouse" => "nullable|string|max:10",
                "basic_information.no_of_children" => "nullable|numeric|max:10"
            ];
        }
    }

    private function emergencyContactRules($id = null): array
    {
        if ($id) {
            return [
                '_id' => ["required", "string"],
                'user_id' => ['required', 'uuid', 'exists:users,id'],
                'emergency_contact' => "required|array",
                "emergency_contact.*.name" => "required|string|max:50",
                "emergency_contact.*.relationship" => "required|string|max:100",
                "emergency_contact.*.phone_1" => "required|digits:10",
                "emergency_contact.*.phone_2" => "nullable|digits:10",
            ];
        } else {
            return [
                'emergency_contact' => "required|array",
                "emergency_contact.*.name" => "required|string|max:50",
                "emergency_contact.*.relationship" => "required|string|max:100",
                "emergency_contact.*.phone_1" => "required|digits:10",
                "emergency_contact.*.phone_2" => "nullable|digits:10",
            ];
        }
    }

    private function aboutRules($id = null): array
    {
        if ($id) {
            return [
                '_id' => ["required", "string"],
                'user_id' => ['required', 'uuid', 'exists:users,id'],
                'basic_information' => "required|array",
                "basic_information.about" => "required|string|max:255",
            ];
        } else {
            return [
                'basic_information' => "required|array",
                "basic_information.about" => "required|string|max:255",
            ];
        }
    }

    private function bankInformationRules($id = null): array
    {
        if ($id) {
            return [
                '_id' => ["required", "string"],
                'user_id' => ['required', 'uuid', 'exists:users,id'],
                'bank_information' => "required|array",
                "bank_information.bank_name" => "required|string|max:255",
                "bank_information.branch_address" => "required|string|max:255",
                "bank_information.account_holder_name" => "required|string|max:255",
                "bank_information.account_number" => "required|string|max:255",
                "bank_information.account_type" => "nullable|string|max:255",
                "bank_information.swift_code" => "nullable|string|max:255",
            ];
        } else {
            return [
                'bank_information' => "required|array",
                "bank_information.bank_name" => "required|string|max:255",
                "bank_information.branch_address" => "required|string|max:255",
                "bank_information.account_holder_name" => "required|string|max:255",
                "bank_information.account_number" => "required|string|max:255",
                "bank_information.account_type" => "nullable|string|max:255",
                "bank_information.swift_code" => "nullable|string|max:255",
            ];
        }
    }

    private function familyInformationRules($id = null): array
    {
        if ($id) {
            return [
                '_id' => ["required", "string"],
                'user_id' => ['required', 'uuid', 'exists:users,id'],
                'family_information' => "required|array",
                "family_information.*.name" => "required|string|max:50",
                "family_information.*.relationship" => "required|string|max:100",
                "family_information.*.phone_1" => "required|digits:10",
                "family_information.*.phone_2" => "nullable|digits:10",
            ];
        } else {
            return [
                'family_information' => "required|array",
                "family_information.*.name" => "required|string|max:50",
                "family_information.*.relationship" => "required|string|max:100",
                "family_information.*.phone_1" => "required|digits:10",
                "family_information.*.phone_2" => "nullable|digits:10",
            ];
        }
    }

    private function statutoryInformationRules($id = null): array
    {
        $verificationStatusRule = Rule::in(['pending', 'verified', 'rejected']);

        if ($id) {
            return [
                '_id' => ["required", "string"],
                'user_id' => ['required', 'uuid', 'exists:users,id'],
                'citizen_investment_trust' => ['required', 'array'],
                'citizen_investment_trust.id_number' => ['required', 'string', 'max:50'],
                'citizen_investment_trust.issue_date' => ['required', 'date'],
                'citizen_investment_trust.expiry_date' => ['nullable', 'date', 'after_or_equal:citizen_investment_trust.issue_date'],
                'citizen_investment_trust.issuing_authority' => ['nullable', 'string', 'max:255'],
                'citizen_investment_trust.image' => ['nullable', 'string', 'max:255'],
                'citizen_investment_trust.verification_status' => ['nullable', 'string', $verificationStatusRule],
                'health_insurance' => ['required', 'array'],
                'health_insurance.id_number' => ['required', 'string', 'max:50'],
                'health_insurance.issue_date' => ['required', 'date'],
                'health_insurance.expiry_date' => ['nullable', 'date', 'after_or_equal:health_insurance.issue_date'],
                'health_insurance.issuing_authority' => ['nullable', 'string', 'max:255'],
                'health_insurance.image' => ['nullable', 'string', 'max:255'],
                'health_insurance.verification_status' => ['nullable', 'string', $verificationStatusRule],
                'police_clearance' => ['required', 'array'],
                'police_clearance.id_number' => ['required', 'string', 'max:50'],
                'police_clearance.issue_date' => ['required', 'date'],
                'police_clearance.expiry_date' => ['nullable', 'date', 'after_or_equal:police_clearance.issue_date'],
                'police_clearance.issuing_authority' => ['nullable', 'string', 'max:255'],
                'police_clearance.image' => ['nullable', 'string', 'max:255'],
                'police_clearance.verification_status' => ['nullable', 'string', $verificationStatusRule],
                'provident_fund' => ['required', 'array'],
                'provident_fund.id_number' => ['required', 'string', 'max:50'],
                'provident_fund.issue_date' => ['required', 'date'],
                'provident_fund.expiry_date' => ['nullable', 'date', 'after_or_equal:provident_fund.issue_date'],
                'provident_fund.issuing_authority' => ['nullable', 'string', 'max:255'],
                'provident_fund.image' => ['nullable', 'string', 'max:255'],
                'provident_fund.verification_status' => ['nullable', 'string', $verificationStatusRule],
                'social_security_fund' => ['required', 'array'],
                'social_security_fund.id_number' => ['required', 'string', 'max:50'],
                'social_security_fund.issue_date' => ['required', 'date'],
                'social_security_fund.expiry_date' => ['nullable', 'date', 'after_or_equal:social_security_fund.issue_date'],
                'social_security_fund.issuing_authority' => ['nullable', 'string', 'max:255'],
                'social_security_fund.image' => ['nullable', 'string', 'max:255'],
                'social_security_fund.verification_status' => ['nullable', 'string', $verificationStatusRule],
                'tax_clearance' => ['required', 'array'],
                'tax_clearance.id_number' => ['required', 'string', 'max:50'],
                'tax_clearance.issue_date' => ['required', 'date'],
                'tax_clearance.expiry_date' => ['nullable', 'date', 'after_or_equal:tax_clearance.issue_date'],
                'tax_clearance.issuing_authority' => ['nullable', 'string', 'max:255'],
                'tax_clearance.image' => ['nullable', 'string', 'max:255'],
                'tax_clearance.verification_status' => ['nullable', 'string', $verificationStatusRule],
            ];
        } else {
            return [
                'citizen_investment_trust' => ['required', 'array'],
                'citizen_investment_trust.id_number' => ['required', 'string', 'max:50'],
                'citizen_investment_trust.issue_date' => ['required', 'date'],
                'citizen_investment_trust.expiry_date' => ['nullable', 'date', 'after_or_equal:citizen_investment_trust.issue_date'],
                'citizen_investment_trust.issuing_authority' => ['nullable', 'string', 'max:255'],
                'citizen_investment_trust.image' => ['nullable', 'string', 'max:255'],
                'citizen_investment_trust.verification_status' => ['nullable', 'string', $verificationStatusRule],
                'health_insurance' => ['required', 'array'],
                'health_insurance.id_number' => ['required', 'string', 'max:50'],
                'health_insurance.issue_date' => ['required', 'date'],
                'health_insurance.expiry_date' => ['nullable', 'date', 'after_or_equal:health_insurance.issue_date'],
                'health_insurance.issuing_authority' => ['nullable', 'string', 'max:255'],
                'health_insurance.image' => ['nullable', 'string', 'max:255'],
                'health_insurance.verification_status' => ['nullable', 'string', $verificationStatusRule],
                'police_clearance' => ['required', 'array'],
                'police_clearance.id_number' => ['required', 'string', 'max:50'],
                'police_clearance.issue_date' => ['required', 'date'],
                'police_clearance.expiry_date' => ['nullable', 'date', 'after_or_equal:police_clearance.issue_date'],
                'police_clearance.issuing_authority' => ['nullable', 'string', 'max:255'],
                'police_clearance.image' => ['nullable', 'string', 'max:255'],
                'police_clearance.verification_status' => ['nullable', 'string', $verificationStatusRule],
                'provident_fund' => ['required', 'array'],
                'provident_fund.id_number' => ['required', 'string', 'max:50'],
                'provident_fund.issue_date' => ['required', 'date'],
                'provident_fund.expiry_date' => ['nullable', 'date', 'after_or_equal:provident_fund.issue_date'],
                'provident_fund.issuing_authority' => ['nullable', 'string', 'max:255'],
                'provident_fund.image' => ['nullable', 'string', 'max:255'],
                'provident_fund.verification_status' => ['nullable', 'string', $verificationStatusRule],
                'social_security_fund' => ['required', 'array'],
                'social_security_fund.id_number' => ['required', 'string', 'max:50'],
                'social_security_fund.issue_date' => ['required', 'date'],
                'social_security_fund.expiry_date' => ['nullable', 'date', 'after_or_equal:social_security_fund.issue_date'],
                'social_security_fund.issuing_authority' => ['nullable', 'string', 'max:255'],
                'social_security_fund.image' => ['nullable', 'string', 'max:255'],
                'social_security_fund.verification_status' => ['nullable', 'string', $verificationStatusRule],
                'tax_clearance' => ['required', 'array'],
                'tax_clearance.id_number' => ['required', 'string', 'max:50'],
                'tax_clearance.issue_date' => ['required', 'date'],
                'tax_clearance.expiry_date' => ['nullable', 'date', 'after_or_equal:tax_clearance.issue_date'],
                'tax_clearance.issuing_authority' => ['nullable', 'string', 'max:255'],
                'tax_clearance.image' => ['nullable', 'string', 'max:255'],
                'tax_clearance.verification_status' => ['nullable', 'string', $verificationStatusRule],
            ];
        }
    }

    private function supportingDocumentsRules($id = null): array
    {
        $verificationStatusRule = Rule::in(['pending', 'verified', 'rejected']);

        if ($id) {
            return [
                '_id' => ["required", "string"],
                'user_id' => ['required', 'uuid', 'exists:users,id'],
                'citizenship' => ['required', 'array'],
                'citizenship.id_number' => ['required', 'string', 'max:50'],
                'citizenship.issue_date' => ['required', 'date'],
                'citizenship.expiry_date' => ['nullable', 'date', 'after_or_equal:citizenship.issue_date'],
                'citizenship.issuing_authority' => ['nullable', 'string', 'max:255'],
                'citizenship.image' => ['nullable', 'string', 'max:255'],
                'citizenship.verification_status' => ['nullable', 'string', $verificationStatusRule],
                'driving_license' => ['required', 'array'],
                'driving_license.id_number' => ['required', 'string', 'max:50'],
                'driving_license.issue_date' => ['required', 'date'],
                'driving_license.expiry_date' => ['nullable', 'date', 'after_or_equal:driving_license.issue_date'],
                'driving_license.issuing_authority' => ['nullable', 'string', 'max:255'],
                'driving_license.image' => ['nullable', 'string', 'max:255'],
                'driving_license.verification_status' => ['nullable', 'string', $verificationStatusRule],
                'national_id' => ['required', 'array'],
                'national_id.id_number' => ['required', 'string', 'max:50'],
                'national_id.issue_date' => ['required', 'date'],
                'national_id.expiry_date' => ['nullable', 'date', 'after_or_equal:national_id.issue_date'],
                'national_id.issuing_authority' => ['nullable', 'string', 'max:255'],
                'national_id.image' => ['nullable', 'string', 'max:255'],
                'national_id.verification_status' => ['nullable', 'string', $verificationStatusRule],
                'pan' => ['required', 'array'],
                'pan.id_number' => ['required', 'string', 'max:50'],
                'pan.issue_date' => ['required', 'date'],
                'pan.expiry_date' => ['nullable', 'date', 'after_or_equal:pan.issue_date'],
                'pan.issuing_authority' => ['nullable', 'string', 'max:255'],
                'pan.image' => ['nullable', 'string', 'max:255'],
                'pan.verification_status' => ['nullable', 'string', $verificationStatusRule],
                'passport' => ['required', 'array'],
                'passport.id_number' => ['required', 'string', 'max:50'],
                'passport.issue_date' => ['required', 'date'],
                'passport.expiry_date' => ['nullable', 'date', 'after_or_equal:passport.issue_date'],
                'passport.issuing_authority' => ['nullable', 'string', 'max:255'],
                'passport.image' => ['nullable', 'string', 'max:255'],
                'passport.verification_status' => ['nullable', 'string', $verificationStatusRule],
            ];
        } else {
            return [
                'citizenship' => ['required', 'array'],
                'citizenship.id_number' => ['required', 'string', 'max:50'],
                'citizenship.issue_date' => ['required', 'date'],
                'citizenship.expiry_date' => ['nullable', 'date', 'after_or_equal:citizenship.issue_date'],
                'citizenship.issuing_authority' => ['nullable', 'string', 'max:255'],
                'citizenship.image' => ['nullable', 'string', 'max:255'],
                'citizenship.verification_status' => ['nullable', 'string', $verificationStatusRule],
                'driving_license' => ['required', 'array'],
                'driving_license.id_number' => ['required', 'string', 'max:50'],
                'driving_license.issue_date' => ['required', 'date'],
                'driving_license.expiry_date' => ['nullable', 'date', 'after_or_equal:driving_license.issue_date'],
                'driving_license.issuing_authority' => ['nullable', 'string', 'max:255'],
                'driving_license.image' => ['nullable', 'string', 'max:255'],
                'driving_license.verification_status' => ['nullable', 'string', $verificationStatusRule],
                'national_id' => ['required', 'array'],
                'national_id.id_number' => ['required', 'string', 'max:50'],
                'national_id.issue_date' => ['required', 'date'],
                'national_id.expiry_date' => ['nullable', 'date', 'after_or_equal:national_id.issue_date'],
                'national_id.issuing_authority' => ['nullable', 'string', 'max:255'],
                'national_id.image' => ['nullable', 'string', 'max:255'],
                'national_id.verification_status' => ['nullable', 'string', $verificationStatusRule],
                'pan' => ['required', 'array'],
                'pan.id_number' => ['required', 'string', 'max:50'],
                'pan.issue_date' => ['required', 'date'],
                'pan.expiry_date' => ['nullable', 'date', 'after_or_equal:pan.issue_date'],
                'pan.issuing_authority' => ['nullable', 'string', 'max:255'],
                'pan.image' => ['nullable', 'string', 'max:255'],
                'pan.verification_status' => ['nullable', 'string', $verificationStatusRule],
                'passport' => ['required', 'array'],
                'passport.id_number' => ['required', 'string', 'max:50'],
                'passport.issue_date' => ['required', 'date'],
                'passport.expiry_date' => ['nullable', 'date', 'after_or_equal:passport.issue_date'],
                'passport.issuing_authority' => ['nullable', 'string', 'max:255'],
                'passport.image' => ['nullable', 'string', 'max:255'],
                'passport.verification_status' => ['nullable', 'string', $verificationStatusRule],
            ];
        }
    }

    private function educationRules($id = null): array
    {
        if ($id) {
            return [
                '_id' => ["required", "string"],
                'user_id' => ['required', 'uuid', 'exists:users,id'],
                'education' => "required|array",
                "education.*.institution_name" => "required|string|max:255",
                "education.*.course" => "required|string|max:255",
                "education.*.start_date" => "required|date",
                "education.*.end_date" => 'nullable|date|after_or_equal:education.*.start_date',
                "education.*.percentage_or_gpa" => "nullable|digits:4",
                "education.*.is_current" => "required|boolean",
            ];
        } else {
            return [
                'education' => "required|array",
                "education.*.institution_name" => "required|string|max:255",
                "education.*.course" => "required|string|max:255",
                "education.*.start_date" => "required|date",
                "education.*.end_date" => 'nullable|date|after_or_equal:education.*.start_date',
                "education.*.percentage_or_gpa" => "nullable|digits:4",
                "education.*.is_current" => "required|boolean",
            ];
        }
    }

    private function experienceRules($id = null): array
    {
        if ($id) {
            return [
                '_id' => ["required", "string"],
                'user_id' => ['required', 'uuid', 'exists:users,id'],
                'experience' => "required|array",
                "experience.*.company_name" => "required|string|max:255",
                "experience.*.designation" => "required|string|max:255",
                "experience.*.start_date" => "required|date",
                "experience.*.end_date" => 'nullable|date|after_or_equal:experience.*.start_date',
                "experience.*.is_current" => "required|boolean",
            ];
        } else {
            return [
                'experience' => "required|array",
                "experience.*.company_name" => "required|string|max:255",
                "experience.*.designation" => "required|string|max:255",
                "experience.*.start_date" => "required|date",
                "experience.*.end_date" => 'nullable|date|after_or_equal:experience.*.start_date',
                "experience.*.is_current" => "required|boolean",
            ];
        }
    }
}
