<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DowntimeEventController;
use App\Http\Controllers\MasterProductionController;
use App\Http\Controllers\MetaController;
use App\Http\Controllers\ProductionRecordController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ShiftAssignmentController;
use Illuminate\Support\Facades\Route;

Route::get('meta', [MetaController::class, 'index']);
Route::get('dashboard', [DashboardController::class, 'index']);

// Master Production (per mesin)
Route::get('master-productions', [MasterProductionController::class, 'index']);
Route::get('master-productions/{machine:name}', [MasterProductionController::class, 'show']);
Route::put('master-productions/{machine:name}', [MasterProductionController::class, 'update']);

// Production
Route::apiResource('production-records', ProductionRecordController::class)
    ->parameters(['production-records' => 'record']);

// Downtime
Route::patch('downtime-events/{event}/finish', [DowntimeEventController::class, 'finish']);
Route::apiResource('downtime-events', DowntimeEventController::class)
    ->parameters(['downtime-events' => 'event']);

// Shift & PIC
Route::apiResource('shift-assignments', ShiftAssignmentController::class)
    ->parameters(['shift-assignments' => 'assignment']);

// Reports
Route::prefix('reports')->group(function () {
    Route::get('downtime', [ReportController::class, 'downtime']);
    Route::get('downtime/export', [ReportController::class, 'exportDowntime']);
    Route::get('downtime/summary/export', [ReportController::class, 'exportDowntimeSummary']);
    Route::get('production', [ReportController::class, 'production']);
    Route::get('production/export', [ReportController::class, 'exportProduction']);
});