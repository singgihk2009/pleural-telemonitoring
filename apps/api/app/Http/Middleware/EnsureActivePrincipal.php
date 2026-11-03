<?php

namespace App\Http\Middleware;

use App\Auth\ResearchRoleCode;
use App\Models\PatientCredential;
use App\Models\StaffUser;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class EnsureActivePrincipal
{
    public function handle(Request $request, Closure $next): mixed
    {
        $actor = $request->user();
        $active = match (true) {
            $actor instanceof StaffUser => $actor->status === 'AKTIF' && ! $actor->hasRole(ResearchRoleCode::Patient),
            $actor instanceof PatientCredential => $actor->revoked_at === null && $actor->participant?->kelompok === 'INTERVENSI' && $actor->participant?->status === 'AKTIF',
            default => false,
        };
        $token = $actor?->currentAccessToken();
        if (! $active || ! $token instanceof PersonalAccessToken || ! $token->can('api') || $token->expires_at === null) {
            throw new AuthenticationException;
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
