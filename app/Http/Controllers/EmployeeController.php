<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
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

            $user =  $this->storeOrUpdateUser($request);
            $employee = Employee::create(["user_id" => $user->id, "deleted_at" => null]);
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

    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $employee = Employee::find($id);
            if (!$employee) {
                return $this->sendErrorOfNotFound404('Employee not found');
            }

            $user = User::find($employee->user_id);
            $user->delete();

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
                $row->setAttribute('first_name',  $user->first_name);
                $row->setAttribute('middle_name', $user->middle_name);
                $row->setAttribute('last_name',   $user->last_name);
                $row->setAttribute('email',       $user->email);
                $row->setAttribute('phone',       $user->phone);
                $row->setAttribute('avatar',      $user->avatar);
            }
            return $row;
        });
    }

    private function storeOrUpdateUser(Request $request): User
    {
        $user = User::findOrNew($request->user_id);

        $user->fill([
            'first_name'  => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name'   => $request->last_name,
            'email'       => $request->email,
            'phone'       => $request->phone,
            'avatar'      => $request->avatar,
        ]);

        if (!$user->exists) {
            $user->password = Hash::make(Str::password(12));
            $user->created_by_id = Auth::id();
        }

        $user->updated_by_id = Auth::id();
        $user->save();
        return $user;
    }
}
