<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\HolidayController;

Route::prefix("v1")->group(function () {
    Route::prefix("auth")->group(function () {
        Route::post('login', [AuthController::class, "login"]);
        Route::post('register', [AuthController::class, "register"]);
        Route::post('forgot-password', [AuthController::class, "forgotPassword"]);
        Route::post('reset-password', [AuthController::class, "resetPassword"]);
        Route::post('refresh', [AuthController::class, 'refreshToken']);

        Route::group(['middleware' => ['auth:api']], function () {
            Route::get('me', [AuthController::class, 'getMe']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    Route::group(["prefix" => "attendance", "middleware" => ["auth:api"]], function () {
        Route::get("/", [AttendanceController::class, "index"]);
        Route::get("/check-attendance", [AttendanceController::class, "checkAttendance"]);
        Route::post("/punch-in", [AttendanceController::class, "punchIn"]);
        Route::post("/punch-out", [AttendanceController::class, "punchOut"]);
    });

    Route::group(["prefix" => "roles", "middleware" => ["auth:api"]], function () {
        Route::get("/", [RoleController::class, "index"]);
        Route::post("/", [RoleController::class, "store"]);
        Route::get("/{id}", [RoleController::class, "show"]);
        Route::put("/{id}", [RoleController::class, "update"]);
        Route::delete("/{id}", [RoleController::class, "destroy"]);
    });

    Route::group(["prefix" => "permissions", "middleware" => ["auth:api"]], function () {
        Route::get("/", [PermissionController::class, "index"]);
    });

    Route::group(["prefix" => "users", "middleware" => ["auth:api"]], function () {
        Route::get("/", [UserController::class, "index"]);
        Route::post("/", [UserController::class, "store"]);
        Route::get("/{id}", [UserController::class, "show"]);
        Route::put("/{id}", [UserController::class, "update"]);
        Route::delete("/{id}", [UserController::class, "destroy"]);
    });

    Route::middleware(['auth:api'])->group(function () {
        Route::get('holidays', [HolidayController::class, 'index']);
        Route::get('holidays/{id}', [HolidayController::class, 'show']);
        Route::post('holidays', [HolidayController::class, 'store']);
        Route::put('holidays/{id}', [HolidayController::class, 'update']);
        Route::delete('holidays/{id}', [HolidayController::class, 'destroy']);
        Route::post('holidays/bulk-import', [HolidayController::class, 'bulkImport']);
    });
});
