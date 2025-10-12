<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $page  = request('page') ? (int) request('page') : 1;
            $limit = request('limit') ? (int) request('limit') : 10;

            $query = Role::query()
                ->with('permissions')
                ->orderByDesc('created_at');

            $total = (clone $query)->count();
            $roles  = $query->skip(value: ($page - 1) * $limit)
                ->take($limit)
                ->get();

            return $this->sendPaginateResponse(
                'Fetch all roles',
                $page,
                $limit,
                $total,
                $roles
            );
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), $this->rules());
            if ($validator->fails()) {
                return $this->sendValidationErrors($validator);
            }

            DB::beginTransaction();

            $role = Role::create([
                'name'         => $request->name . " - " . Str::uuid(),
                'description'  => $request->description,
                'is_superuser' => (bool) $request->boolean(key: 'is_superuser'),
            ]);

            if ($request->filled('permissions') && is_array($request->permissions)) {
                $role->permissions()->attach($request->permissions);
            }

            $role->load('permissions');

            DB::commit();
            return $this->sendSuccessResponse('Role created successfully', $role);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $role = Role::find($id);
            if (!$role) {
                return $this->sendErrorOfNotFound404('Role not found');
            }

            return $this->sendSuccessResponse('Fetch one role', $role->load('permissions'));
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $role = Role::find($id);
            if (!$role) {
                return $this->sendErrorOfNotFound404('Role not found');
            }

            $role->load('permissions');

            $validator = Validator::make($request->all(), $this->rules($role->id));
            if ($validator->fails()) {
                return $this->sendValidationErrors($validator);
            }

            $role->fill(
                [
                    'description'  => $request->has('description')  ? $request->description  : $role->description,
                ]
            )->save();

            if ($request->has('permissions')) {
                $ids = is_array($request->permissions) ? $request->permissions : [];
                $role->permissions()->sync($ids);
            }

            $role->load('permissions');

            DB::commit();

            return $this->sendSuccessResponse('Role updated successfully', $role);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $role = Role::find($id);
            if (!$role) {
                return $this->sendErrorOfNotFound404('Role not found');
            }

            $role->withCount('users');
            if ($role->users_count > 0) {
                return $this->sendErrorOfUnprocessableEntity(
                    "Cannot delete role because it is assigned to {$role->users_count} user(s)."
                );
            }

            $role->permissions()->detach();
            $role->delete();

            DB::commit();

            return $this->sendSuccessResponse('Role deleted successfully', $role);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    private function rules($id = null): array
    {
        if ($id) {
            return [
                'name'         => ['sometimes', 'string', 'max:100'],
                'description'  => ['sometimes', 'nullable', 'string', 'max:255'],
                'is_superuser' => ['sometimes', 'boolean'],
                'permissions'  => ['sometimes', 'array'],
                'permissions.*' => ['uuid', Rule::exists('permissions', 'id')],
            ];
        } else {
            return [
                'name'         => ['required', 'string', 'max:100'],
                'description'  => ['nullable', 'string', 'max:255'],
                'is_superuser' => ['sometimes', 'boolean'],
                'permissions'  => ['sometimes', 'array'],
                'permissions.*' => ['uuid', Rule::exists('permissions', 'id')],
            ];
        }
    }
}
