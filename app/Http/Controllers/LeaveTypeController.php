<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LeaveTypeController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $page  = (int) request('page', 1);
            $limit = (int) request('limit', 10);

            $query = LeaveType::query();

            $total = (clone $query)->count();
            $rows  = $query->skip(($page - 1) * $limit)->take($limit)->get();

            return $this->sendPaginateResponse(
                'Fetch all leave types',
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

            $data = $validator->validated();
            $data["created_by_id"] = Auth::id();
            $data["updated_by_id"] = Auth::id();

            $leaveType = LeaveType::create($data);

            return $this->sendSuccessResponse("Leave type created successfully", $leaveType);
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $leaveType = LeaveType::find($id);
            if (!$leaveType) {
                return $this->sendErrorOfNotFound404("Leave type not found");
            }

            return $this->sendSuccessResponse("Fetch one leave type", $leaveType);
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), $this->rules($id));
            if ($validator->fails()) {
                return $this->sendValidationErrors($validator);
            }

            $data = $validator->validated();
            $data["updated_by_id"] = Auth::id();

            $leaveType = LeaveType::find($id);
            if (!$leaveType) {
                return $this->sendErrorOfNotFound404("Leave type not found");
            }

            $leaveType->update($data);

            return $this->sendSuccessResponse("Leave type updated successfully", $leaveType);
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $leaveType = LeaveType::find($id);
            if (!$leaveType) {
                return $this->sendErrorOfNotFound404("Leave type not found");
            }

            $leaveType->delete();

            return $this->sendSuccessResponse("Leave deleted successfully", $leaveType);
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    private function rules($id = null): array
    {
        if ($id) {
            return [
                'name' => 'required|string|max:100|unique:leave_types,name',
                'is_paid' => 'required|boolean',
                'description' => 'nullable|string|max:255'
            ];
        } else {
            return [
                'name' => 'required|string|max:100|unique:leave_types,name',
                'is_paid' => 'required|boolean',
                'description' => 'nullable|string|max:255'
            ];
        }
    }
}
