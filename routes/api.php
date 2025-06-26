<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\OutletController;
use App\Http\Controllers\API\OutletHistoryController;
use App\Http\Controllers\API\PlanVisitController;
use App\Http\Controllers\API\ReferenceController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\VisitController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login']);
Route::post('send-otp', [AuthController::class, 'sendOtp']);
Route::post('verify-otp', [AuthController::class, 'verifyOtp']);

Route::middleware('auth:sanctum')->group(function () {
    // USER
    Route::get('profile', [AuthController::class, 'profile']);
    Route::post('logout', [AuthController::class, 'logout']);

    // OUTLET
    Route::get('outlets', [OutletController::class, 'index']);
    Route::get('outlets/{id}', [OutletController::class, 'show']);
    Route::get('outlets/{id}/with-custom-fields', [OutletController::class, 'showWithCustomFields']);
    Route::post('outlets', [OutletController::class, 'store']);
    Route::post('outlets/{id}', [OutletController::class, 'update']);

    // OUTLET HISTORY MANAGEMENT
    Route::post('outlets/{outlet}/history-change', [OutletHistoryController::class, 'requestOutletHistory']);
    Route::get('outlets/{outlet}/history', [OutletHistoryController::class, 'history']);
    Route::get('outlet-histories/pending', [OutletHistoryController::class, 'pendingApprovals']);
    Route::post('outlet-histories/{history}/process', [OutletHistoryController::class, 'processApproval']);

    // VISIT
    Route::get('visits', [VisitController::class, 'index']);
    Route::get('visits/check', [VisitController::class, 'check']);
    Route::get('visits/{id}', [VisitController::class, 'show']);
    Route::post('visits', [VisitController::class, 'store']);
    Route::post('visits/{id}', [VisitController::class, 'update']);
    Route::delete('visits/{id}', [VisitController::class, 'destroy']);

    // PLAN VISIT
    Route::get('plan-visits', [PlanVisitController::class, 'index']);
    Route::post('plan-visits', [PlanVisitController::class, 'store']);
    Route::put('plan-visits/{id}', [PlanVisitController::class, 'update']);
    Route::delete('plan-visits/{id}', [PlanVisitController::class, 'destroy']);

    Route::apiResource('user', UserController::class);

    Route::get('references/badan-usaha', [ReferenceController::class, 'badanUsaha']);
    Route::get('references/division', [ReferenceController::class, 'division']);
    Route::get('references/region', [ReferenceController::class, 'region']);
    Route::get('references/cluster', [ReferenceController::class, 'cluster']);
    Route::get('references/role', [ReferenceController::class, 'role']);
    Route::get('references/custom-fields', [ReferenceController::class, 'customFields']);
    Route::get('references/custom-field-values', [ReferenceController::class, 'customFieldValues']);
    Route::get('references/outlet-level-fields', [ReferenceController::class, 'outletLevelFields']);
});
