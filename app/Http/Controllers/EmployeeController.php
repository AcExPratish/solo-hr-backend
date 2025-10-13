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
                    $this->storeOrUpdateUser($request);
                    $this->storeOrUpdateBasicInformation($request, $employee);
                    break;

                case EmployeeFormTypeEnum::PersonalInformation->value:
                    $this->storeOrUpdateBasicInformation($request, $employee);
                    break;

                case EmployeeFormTypeEnum::EmergencyContact->value:
                    $this->storeOrUpdateEmergencyContact($request, $employee);
                    break;

                case EmployeeFormTypeEnum::About->value:
                    $this->storeOrUpdateBasicInformation($request, $employee);
                    break;

                case EmployeeFormTypeEnum::BankInformation->value:
                    $this->storeOrUpdateBankInformation($request, $employee);
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
        $clean   = [];

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
}
