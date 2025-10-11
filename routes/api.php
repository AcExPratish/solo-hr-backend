<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AttendanceController;

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
});
