<?php

namespace App\Http\Controllers;

use App\Auth\SecurityAudit;
use App\Models\Participant;
use App\Models\PatientCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class HealthItuesController extends Controller
{
    public function review(Request $request, SecurityAudit $audit): JsonResponse
    {
        Gate::authorize('create', Participant::class);
        $instrument = $this->baseline();
        $audit->record($request, 'health_itues.reviewed', 'AUTHORIZED', $instrument['version'], 'health_itues');

        return response()->json(['data' => $instrument])->header('Cache-Control', 'no-store');
    }

    public function status(Request $request): JsonResponse
    {
        abort_unless($request->user() instanceof PatientCredential, 403);
        $preview = app()->environment('uat')
            && config('assessment.synthetic_uat') === true
            && DB::connection()->getDriverName() === 'mysql'
            && DB::connection()->getDatabaseName() === 'telenapas_synthetic_uat_test';

        return response()->json([
            'data' => ['can_submit' => false, 'preview' => $preview ? $this->baseline() : null],
        ])->header('Cache-Control', 'no-store');
    }

    private function baseline(): array
    {
        return json_decode(file_get_contents(resource_path('instruments/health-itues-v1.0.json')), true, flags: JSON_THROW_ON_ERROR);
    }
}
