<?php

namespace App\Http\Controllers;

use App\Models\LeavePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LeavePolicyController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $page  = (int) request('page', 1);
            $limit = (int) request('limit', 10);

            $query = LeavePolicy::query()
                ->with(['user', 'type']);

            $total = (clone $query)->count();
            $rows  = $query->skip(($page - 1) * $limit)->take($limit)->get();

            return $this->sendPaginateResponse(
                'Fetch all leave policies',
                $page,
                $limit,
                $total,
                $rows
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

            $data['created_by_id'] = Auth::id();
            $data['updated_by_id'] = Auth::id();

            $existingPolicy = LeavePolicy::where('user_id', $data['user_id'])
                ->where('leave_type_id', $data['leave_type_id'])
                ->first();
            if ($existingPolicy) {
                return $this->sendErrorOfUnprocessableEntity("Policy already exists for this user and leave type");
            }

            $leavePolicy = LeavePolicy::create($data);

            return $this->sendSuccessResponse("Leave policy created successfully", $leavePolicy->load('type', 'user'));
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $leavePolicy = LeavePolicy::find($id);
            if (!$leavePolicy) {
                return $this->sendErrorOfNotFound404("Leave policy not found");
            }

            return $this->sendSuccessResponse("Fetch one leave policy", $leavePolicy->load('type', 'user'));
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            if (!$request->has('remaining_days')) {
                $request->merge(['remaining_days' => $request->input('total_days', 0)]);
            }

            $validator = Validator::make($request->all(), $this->rules($id));
            if ($validator->fails()) {
                return $this->sendValidationErrors($validator);
            }

            $leavePolicy = LeavePolicy::find($id);
            if (!$leavePolicy) {
                return $this->sendErrorOfNotFound404("Leave policy not found");
            }

            $data = $validator->validated();
            $data["updated_by_id"] = Auth::id();
            $leavePolicy->update($data);

            return $this->sendSuccessResponse("Leave policy updated successfully", $leavePolicy->load('type', 'user'));
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $leavePolicy = LeavePolicy::find($id);
            if (!$leavePolicy) {
                return $this->sendErrorOfNotFound404("Leave policy not found");
            }

            $leavePolicy->delete();

            return $this->sendSuccessResponse("Leave policy deleted successfully", $leavePolicy);
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    private function rules($id = null): array
    {
        if ($id) {
            return [
                'leave_type_id'  => 'required|exists:leave_types,id',
                'user_id'        => 'required|exists:users,id',
                'policy_name'    => 'nullable|string|max:150',
                'total_days'     => 'required|integer|min:0',
                'remaining_days' => 'nullable|integer|min:0'
            ];
        } else {
            return [
                'leave_type_id'  => 'required|exists:leave_types,id',
                'user_id'        => 'required|exists:users,id',
                'policy_name'    => 'nullable|string|max:150',
                'total_days'     => 'required|integer|min:0',
                'remaining_days' => 'nullable|integer|min:0'
            ];
        }
    }
}
