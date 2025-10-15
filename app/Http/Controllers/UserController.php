<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $page  = request('page') ? (int) request('page') : 1;
            $limit = request('limit') ? (int) request('limit') : 10;

            $query = User::query()
                ->where('id', '!=', Auth::id())
                ->with(['roles:id,name'])
                ->orderByDesc('created_at');

            $total = (clone $query)->count();
            $users = $query
                ->skip(($page - 1) * $limit)
                ->take($limit)
                ->get();

            return $this->sendPaginateResponse(
                'Fetch all users',
                $page,
                $limit,
                $total,
                $users
            );
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), $this->rules());
            if ($validator->fails()) {
                return $this->sendValidationErrors($validator);
            }
            $roleIds = (array) $request->input('roles', []);
            $rolesCount = Role::whereIn('id', $roleIds)->count();
            if ($rolesCount === 0) {
                return $this->sendErrorOfBadResponse('Invalid roles');
            }

            $user = User::create([
                'first_name'    => $request->first_name,
                'middle_name'   => $request->middle_name,
                'last_name'     => $request->last_name,
                'phone'         => $request->phone,
                'avatar'        => $request->avatar,
                'email'         => $request->email,
                'password'      => Hash::make($request->password),
                'created_by_id' => Auth::id(),
                'updated_by_id' => Auth::id()
            ]);

            if (!empty($roleIds)) {
                $user->roles()->attach($roleIds);
            }

            $user->load(['roles:id,name']);

            DB::commit();
            return $this->sendSuccessResponse('User created successfully', $user);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $user = User::with(['roles:id,name'])->find($id);
            if (!$user) {
                return $this->sendErrorOfNotFound404('User not found');
            }

            return $this->sendSuccessResponse('Fetch one user', $user);
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = User::with(['roles:id,name'])->find($id);
            if (! $user) {
                return $this->sendErrorOfNotFound404('User not found');
            }

            $validator = Validator::make($request->all(), $this->rules($user->id));
            if ($validator->fails()) {
                return $this->sendValidationErrors($validator);
            }

            $roleIds = null;
            if ($request->has('roles')) {
                $roleIds = (array) $request->input('roles', []);
                $rolesCount = Role::whereIn('id', $roleIds)->count();
                if ($rolesCount === 0) {
                    return $this->sendErrorOfBadResponse('Invalid roles');
                }
            }

            $data = [
                'first_name'    => $request->has('first_name')  ? $request->first_name  : $user->first_name,
                'middle_name'   => $request->has('middle_name') ? $request->middle_name : $user->middle_name,
                'last_name'     => $request->has('last_name')   ? $request->last_name   : $user->last_name,
                'phone'         => $request->has('phone')       ? $request->phone       : $user->phone,
                'avatar'        => $request->has('avatar')      ? $request->avatar      : $user->avatar,
                'email'         => $request->has('email')       ? $request->email       : $user->email,
                'updated_by_id' => Auth::id(),
            ];

            if ($request->filled('password') && trim($request->password) !== '') {
                $data['password'] = Hash::make($request->password);
            }

            $user->fill($data)->save();
            if (!is_null($roleIds)) {
                $user->roles()->sync($roleIds);
            }

            $user->load(['roles:id,name']);

            DB::commit();
            return $this->sendSuccessResponse('User updated successfully', $user);
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return $this->sendErrorOfNotFound404('User not found');
            }

            $user->delete();

            return $this->sendSuccessResponse('User deleted successfully', $user);
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    private function rules($id = null): array
    {
        if ($id) {
            return [
                'first_name'  => ['required', 'string', 'max:100'],
                'middle_name' => ['nullable', 'nullable', 'string', 'max:100'],
                'last_name'   => ['required', 'string', 'max:100'],
                'phone'       => ['required', 'digits:10'],
                'avatar'      => ['sometimes', 'nullable', 'string', 'max:255'],
                'email'       => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
                'password'    => ['sometimes', 'nullable', 'string', 'min:8'],
                'roles'       => ['required', 'array'],
                'roles.*'     => ['uuid', Rule::exists('roles', 'id')],
            ];
        } else {
            return [
                'first_name'  => ['required', 'string', 'max:100'],
                'middle_name' => ['nullable', 'string', 'max:100'],
                'last_name'   => ['required', 'string', 'max:100'],
                'phone'       => ['required', 'digits:10'],
                'avatar'      => ['sometimes', 'nullable', 'string', 'max:255'],
                'email'       => ['required', 'email', 'max:255', 'unique:users,email'],
                'password'    => ['required', 'string', 'min:8'],
                'roles'       => ['required', 'array'],
                'roles.*'     => ['uuid', Rule::exists('roles', 'id')],
            ];
        }
    }
}
