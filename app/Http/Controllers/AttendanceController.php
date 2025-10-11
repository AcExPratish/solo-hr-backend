<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $page = request('page') ? request('page') : 1;
            $limit = request('limit') ? request('limit') : 20;

            $attendances = Attendance::skip(($page - 1) * $limit)
                ->filterByDate()
                ->filterByUserId()
                ->take($limit)
                ->orderByDesc('date')
                ->get();

            return $this->sendPaginateResponse(
                "List of attendances",
                $page,
                $limit,
                Attendance::count(),
                $attendances
            );
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function checkAttendance(): JsonResponse
    {
        try {
            $attendance =  Attendance::where('date', Carbon::today())
                ->where("user_id", Auth::id())
                ->first();
            if (!$attendance) {
                return $this->sendErrorOfNotFound404("Attendance not found for today");
            }

            return $this->sendSuccessResponse("Attendance check successful", $attendance);
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function punchIn(Request $request): JsonResponse
    {
        try {
            $attendance =  Attendance::where('date', Carbon::today())
                ->where("user_id", Auth::id())
                ->first();
            if ($attendance) {
                return $this->sendErrorOfBadResponse("Attendance already exists");
            }

            $validator = Validator::make($request->all(), [
                'in_note' => 'nullable|string|max:100',
            ]);
            if ($validator->fails()) {
                return $this->sendValidationErrors($validator);
            }

            $attendance = Attendance::create([
                "user_id" => Auth::id(),
                "date" => Carbon::today(),
                "clock_in" => Carbon::now(),
                "in_note" => $request->in_note
            ]);

            return $this->sendSuccessResponse("Attendance recorded successfully", $attendance);
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }

    public function punchOut(Request $request): JsonResponse
    {
        try {
            $attendance =  Attendance::where('date', Carbon::today())
                ->where("user_id", Auth::id())
                ->first();
            if (!$attendance) {
                return $this->sendErrorOfNotFound404("Attendance not found for today");
            }

            $validator = Validator::make($request->all(), [
                'out_note' => 'nullable|string|max:100',
            ]);
            if ($validator->fails()) {
                return $this->sendValidationErrors($validator);
            }

            $attendance->update([
                "clock_out" => Carbon::now(),
                "out_note" => $request->out_note
            ]);

            return $this->sendSuccessResponse("Attendance recorded successfully", $attendance);
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }
}
