<?php

use App\Http\Controllers\AdminDataController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DowntimeEventController;
use App\Http\Controllers\MasterProductionController;
use App\Http\Controllers\MetaController;
use App\Http\Controllers\ProductionRecordController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ShiftAssignmentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ---------- Auth ----------
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('sso', [AuthController::class, 'sso'])->middleware('throttle:10,1');   // <-- BARIS BARU

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::put('password', [AuthController::class, 'changePassword']);
    });
});

// ---------- Semua endpoint di bawah ini wajib login ----------
Route::middleware('auth:sanctum')->group(function () {

    // ===== Operator & admin: baca + input =====
    Route::get('meta', [MetaController::class, 'index']);
    Route::get('dashboard', [DashboardController::class, 'index']);

    // Master Production (per mesin): baca
    Route::get('master-productions', [MasterProductionController::class, 'index']);
    Route::get('master-productions/{machine:name}', [MasterProductionController::class, 'show']);

    // Production
    Route::apiResource('production-records', ProductionRecordController::class)
        ->parameters(['production-records' => 'record'])
        ->only(['index', 'show', 'store', 'update']);

    // Downtime
    Route::patch('downtime-events/{event}/finish', [DowntimeEventController::class, 'finish']);
    Route::apiResource('downtime-events', DowntimeEventController::class)
        ->parameters(['downtime-events' => 'event'])
        ->only(['index', 'show', 'store', 'update']);

    // Shift & PIC
    Route::post('shift-assignments/bulk', [ShiftAssignmentController::class, 'bulkUpsert']);
    Route::apiResource('shift-assignments', ShiftAssignmentController::class)
        ->parameters(['shift-assignments' => 'assignment'])
        ->only(['index', 'show', 'store', 'update']);

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('downtime', [ReportController::class, 'downtime']);
        Route::get('downtime/export', [ReportController::class, 'exportDowntime']);
        Route::get('downtime/summary/export', [ReportController::class, 'exportDowntimeSummary']);
        Route::get('production', [ReportController::class, 'production']);
        Route::get('production/export', [ReportController::class, 'exportProduction']);
    });

    // ===== Admin saja: hapus data, edit master, kelola user =====
    Route::middleware('role:admin')->group(function () {
        Route::put('master-productions/{machine:name}', [MasterProductionController::class, 'update']);

        Route::delete('production-records/{record}', [ProductionRecordController::class, 'destroy']);
        Route::delete('downtime-events/{event}', [DowntimeEventController::class, 'destroy']);
        Route::delete('shift-assignments/{assignment}', [ShiftAssignmentController::class, 'destroy']);

        Route::post('admin/reset-operational-data', [AdminDataController::class, 'resetOperationalData']);

        // harus sebelum apiResource('users') supaya tidak tertabrak users/{user}
        Route::put('users/{user}/password', [UserController::class, 'resetPassword']);
        Route::apiResource('users', UserController::class);
    });
});