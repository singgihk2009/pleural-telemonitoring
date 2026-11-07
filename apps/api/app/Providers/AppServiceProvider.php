<?php

namespace App\Providers;

use App\Auth\SecurityAudit;
use App\Models\Participant;
use App\Models\PatientCredential;
use App\Models\StaffUser;
use App\Policies\ParticipantPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Relation::enforceMorphMap(['staff' => StaffUser::class, 'patient' => PatientCredential::class]);
        Gate::policy(Participant::class, ParticipantPolicy::class);
        RateLimiter::for('assessment', fn (Request $request) => Limit::perMinute(30)->by('assessment-'.$request->user()->getMorphClass().'-'.$request->user()->getAuthIdentifier()));
        foreach (['staff' => 'email', 'patient' => 'code'] as $kind => $field) {
            RateLimiter::for($kind.'-login', function (Request $request) use ($kind, $field) {
                $value = $request->input($field);
                $identity = is_string($value) ? mb_strtolower(trim($value)) : 'INVALID';
                $hash = fn (string $value) => hash_hmac('sha256', $value, config('app.key'));
                $response = function (Request $request, array $headers) {
                    app(SecurityAudit::class)->record($request, 'login.failed', 'THROTTLED');

                    return response()->json(['message' => 'Too many login attempts. Try again later.'], 429, $headers);
                };

                return [
                    Limit::perMinutes(15, 5)->by($kind.'-account-'.$hash($identity))->response($response),
                    Limit::perMinute(30)->by($kind.'-ip-'.$hash($request->ip() ?? 'UNKNOWN'))->response($response),
                ];
            });
        }
    }
}
