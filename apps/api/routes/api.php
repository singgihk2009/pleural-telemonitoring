<?php

use App\Http\Controllers\AccessGrantController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CredentialController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\InstrumentPublicationController;
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
    Route::middleware('throttle:assessment')->group(function () {
        Route::get('/patient/assessments/status', [AssessmentController::class, 'status']);
        Route::post('/patient/assessments', [AssessmentController::class, 'store']);
        Route::get('/patient/assessments', [AssessmentController::class, 'history']);
        Route::get('/participants/{participant}/assessments', [AssessmentController::class, 'researcherHistory']);
    });
    Route::get('/research/daily-instruments', [InstrumentPublicationController::class, 'index'])->middleware('throttle:assessment');
    Route::post('/research/daily-instruments/publish', [InstrumentPublicationController::class, 'publish'])->middleware('throttle:assessment');
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
    Route::get('/participants/{participant}/credentials', [CredentialController::class, 'status']);
    Route::post('/participants/{participant}/credentials', [CredentialController::class, 'issue']);
    Route::delete('/participants/{participant}/credentials', [CredentialController::class, 'revoke']);
    Route::post('/participants/{participant}/identified-access', [AccessGrantController::class, 'store']);
    Route::delete('/participants/{participant}/identified-access/{grant}', [AccessGrantController::class, 'destroy'])->whereNumber('grant');
});
