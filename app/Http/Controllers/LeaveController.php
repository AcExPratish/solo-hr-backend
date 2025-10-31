<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use App\Models\LeavePolicy;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LeaveController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $page  = (int) request('page', 1);
            $limit = (int) request('limit', 10);
            $user_id = (string) request('user_id', '');
            $status = (string) request('status', '');

            $query = Leave::query()
                ->with(['user', 'approver'])
                ->when(!empty($user_id), fn($q) => $q->where('user_id', $user_id))
                ->when(!empty($status), fn($q) => $q->where('status', $status));

            $total = (clone $query)->count();
            $rows  = $query->skip(($page - 1) * $limit)->take($limit)->get();

            return $this->sendPaginateResponse(
                'Fetch all leaves',
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

            $from = Carbon::parse($request->from_date);
            $to   = Carbon::parse($request->to_date);
            $totalDays = $from->diffInDays($to) + 1;

            $policy = LeavePolicy::where('user_id', $request->user_id)
                ->where('leave_type_id', $request->leave_type_id)
                ->first();
            if (!$policy) {
                return $this->sendErrorOfUnprocessableEntity("No policy set for this leave type");
            }

            if ($policy->remaining_days < $request->total_days) {
                return $this->sendErrorOfUnprocessableEntity("Insufficent remaining days");
            }

            $leave = Leave::create([
                'user_id'       => $request->user_id,
                'leave_type_id' => $request->leave_type_id,
                'from_date'     => $from,
                'to_date'       => $to,
                'total_days'    => $totalDays,
                'reason'        => $request->reason,
                'status'        => 'pending',
                'created_by_id' => Auth::id(),
                'updated_by_id' => Auth::id()
            ]);

            return $this->sendSuccessResponse('Leave created successfully', $leave->load('user'));
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $leave = Leave::find($id);
            if (!$leave) {
                return $this->sendErrorOfNotFound404("Leave not found");
            }

            return $this->sendSuccessResponse('Fetch one leave', $leave->load('user', 'approver'));
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

            $from = Carbon::parse($request->from_date);
            $to   = Carbon::parse($request->to_date);
            $totalDays = $from->diffInDays($to) + 1;

            $leave = Leave::find($id);
            if (!$leave) {
                return $this->sendErrorOfNotFound404("Leave not found");
            }

            if ($leave->status !== 'pending') {
                return $this->sendErrorOfUnprocessableEntity("Only pending leaves can be edited");
            }

            $leave->update([
                'leave_type_id' => $request->leave_type_id,
                'from_date'     => $from,
                'to_date'       => $to,
                'total_days'    => $totalDays,
                'reason'        => $request->reason,
                'approved_by_id' => Auth::id(),
                'updated_by_id' => Auth::id()
            ]);

            return $this->sendSuccessResponse('Leave updated successfully', $leave->load('user', 'approver'));
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $leave = Leave::find($id);
            if (!$leave) {
                return $this->sendErrorOfNotFound404("Leave not found");
            }

            if ($leave->status === 'approved') {
                return $this->sendErrorOfUnprocessableEntity("Cannot delete approved leave");
            }

            $leave->delete();

            return $this->sendSuccessResponse('Leave deleted successfully', $leave);
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function decide(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), ["action" => "required|in:approved,rejected"]);
            if ($validator->fails()) {
                return $this->sendValidationErrors($validator);
            }

            $leave = Leave::find($id);
            if (!$leave) {
                return $this->sendErrorOfNotFound404("Leave not found");
            }

            if ($leave->status !== 'pending') {
                return $this->sendErrorOfUnprocessableEntity("Already decided");
            }

            if ($request->action === "rejected") {
                $leave->update([
                    'status' => 'rejected',
                    'approved_by_id' => Auth::id(),
                    'updated_by_id' => Auth::id()
                ]);
                return $this->sendSuccessResponse("Leave rejected", $leave->load('user', 'approver'));
            }

            $policy = LeavePolicy::where('user_id', $leave->user_id)
                ->where('leave_type_id', $leave->leave_type_id)
                ->lockForUpdate()
                ->first();
            if (!$policy || $policy->remaining_days < $leave->total_days) {
                return $this->sendErrorOfUnprocessableEntity("Insufficient remaining days at approval time");
            }

            $policy->decrement('remaining_days', $leave->total_days);
            $leave->update([
                'status' => 'approved',
                'approved_by_id' => Auth::id(),
                'updated_by_id' => Auth::id()
            ]);

            return $this->sendSuccessResponse("Leave approved", $leave->load('user', 'approver'));
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    private function rules($id = null): array
    {
        $statusRule = Rule::in(['pending', 'approved', 'rejected']);
        if ($id) {
            return [
                'user_id' => 'required|exists:users,id',
                'leave_type_id' => 'required|exists:leave_types,id',
                'from_date' => 'required|date',
                'to_date' => 'required|date|after_or_equal:from_date',
                'reason' => 'nullable|string|max:255',
                'status' => ['required', 'string', $statusRule]
            ];
        } else {
            return [
                'user_id' => 'required|exists:users,id',
                'leave_type_id' => 'required|exists:leave_types,id',
                'from_date' => 'required|date',
                'to_date' => 'required|date|after_or_equal:from_date',
                'reason' => 'nullable|string|max:255',
                'status' => ['required', 'string', $statusRule]
            ];
        }
    }
}
