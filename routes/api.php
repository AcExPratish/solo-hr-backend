<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FileController;

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
        Route::get("/", [RoleController::class, "index"])->middleware('perm:roles.view');
        Route::post("/", [RoleController::class, "store"])->middleware('perm:roles.create');
        Route::get("/{id}", [RoleController::class, "show"])->middleware('perm:roles.view');
        Route::put("/{id}", [RoleController::class, "update"])->middleware('perm:roles.update');
        Route::delete("/{id}", [RoleController::class, "destroy"])->middleware('perm:roles.delete');
    });

    Route::group(["prefix" => "permissions", "middleware" => ["auth:api"]], function () {
        Route::get("/", [PermissionController::class, "index"])->middleware('perm:permissions.view');
    });

    Route::group(["prefix" => "users", "middleware" => ["auth:api"]], function () {
        Route::get("/", [UserController::class, "index"])->middleware('perm:users.view');
        Route::post("/", [UserController::class, "store"])->middleware('perm:users.create');
        Route::get("/{id}", [UserController::class, "show"])->middleware('perm:users.view');
        Route::put("/{id}", [UserController::class, "update"])->middleware('perm:users.update');
        Route::delete("/{id}", [UserController::class, "destroy"])->middleware('perm:users.delete');
    });

    Route::group(["prefix" => "holidays", "middleware" => ["auth:api"]], function () {
        Route::get('/', [HolidayController::class, 'index'])->middleware('perm:holidays.view');
        Route::post('/', [HolidayController::class, 'store'])->middleware('perm:holidays.create');
        Route::get('/{id}', [HolidayController::class, 'show'])->middleware('perm:holidays.view');
        Route::put('/{id}', [HolidayController::class, 'update'])->middleware('perm:holidays.update');
        Route::delete('/{id}', [HolidayController::class, 'destroy'])->middleware('perm:holidays.delete');
        Route::post('/bulk-import', [HolidayController::class, 'bulkImport'])->middleware('perm:holidays.create');
    });

    Route::group(["prefix" => "employees", "middleware" => ["auth:api"]], function () {
        Route::get('/', [EmployeeController::class, "index"])->middleware("perm:employees.view");
        Route::post('/', [EmployeeController::class, 'store'])->middleware('perm:employees.create');
        Route::get('/{id}', [EmployeeController::class, 'show'])->middleware('perm:employees.view');
        Route::put('/{id}/{slug}', [EmployeeController::class, 'update'])->middleware('perm:employees.update');
        Route::delete('/{id}', [EmployeeController::class, 'destroy'])->middleware('perm:employees.delete');
    });

    Route::group(["prefix" => "file"], function () {
        Route::post('/upload', [FileController::class, 'upload']);
    });
});
