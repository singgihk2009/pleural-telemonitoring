<?php

use App\Http\Controllers\AccessGrantController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CredentialController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\ParticipantController;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    try {
        DB::select('SELECT 1');
    } catch (QueryException) {
        return response()->json(['status' => 'unavailable', 'database' => 'disconnected'], 503);
    }

    return response()->json(['status' => 'ok', 'database' => 'connected']);
});

Route::post('/auth/staff/login', [AuthController::class, 'staffLogin'])->middleware('throttle:staff-login')->name('auth.staff.login');
Route::post('/auth/patient/login', [AuthController::class, 'patientLogin'])->middleware('throttle:patient-login')->name('auth.patient.login');

Route::middleware(['auth:sanctum', 'principal.active'])->group(function () {
    Route::get('/research/access', [EnrollmentController::class, 'access']);
    Route::post('/research/participants/search', [EnrollmentController::class, 'search']);
    Route::post('/research/participants', [EnrollmentController::class, 'store']);
    Route::get('/research/participants/{participant}', [EnrollmentController::class, 'show']);
    Route::patch('/research/participants/{participant}', [EnrollmentController::class, 'update']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
    Route::delete('/staff/{staff}/tokens', [CredentialController::class, 'revokeStaffTokens'])->whereNumber('staff');
    Route::get('/participants', [ParticipantController::class, 'index']);
    Route::get('/participants/{participant}', [ParticipantController::class, 'show']);
    foreach (['contact', 'entries', 'classifications', 'alerts', 'outcome'] as $resource) {
        Route::get('/participants/{participant}/'.$resource, [ParticipantController::class, $resource]);
    }
    Route::get('/participants/{participant}/follow-ups', [ParticipantController::class, 'followUps']);
    Route::post('/participants/{participant}/credentials', [CredentialController::class, 'issue']);
    Route::delete('/participants/{participant}/credentials', [CredentialController::class, 'revoke']);
    Route::post('/participants/{participant}/identified-access', [AccessGrantController::class, 'store']);
    Route::delete('/participants/{participant}/identified-access/{grant}', [AccessGrantController::class, 'destroy'])->whereNumber('grant');
});
