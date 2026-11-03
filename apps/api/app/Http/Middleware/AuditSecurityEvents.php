<?php

namespace App\Http\Middleware;

use App\Auth\SecurityAudit;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AuditSecurityEvents
{
    public function handle(Request $request, Closure $next): mixed
    {
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            if ($request->is('api/v1/*')) {
                if ($exception instanceof AuthenticationException || $exception instanceof AuthorizationException || ($exception instanceof HttpExceptionInterface && in_array($exception->getStatusCode(), [401, 403], true))) {
                    app(SecurityAudit::class)->record($request, 'permission.denied', 'DENIED');
                } elseif ($exception instanceof ValidationException && $request->routeIs('auth.*.login')) {
                    app(SecurityAudit::class)->record($request, 'login.failed', 'INVALID_REQUEST');
                }
            }
            throw $exception;
        }
        if ($request->is('api/v1/*') && in_array($response->getStatusCode(), [401, 403], true) && ! $request->routeIs('auth.*.login')) {
            app(SecurityAudit::class)->record($request, 'permission.denied', 'DENIED');
        }

        if ($request->routeIs('auth.*.login') && $response->getStatusCode() === 422) {
            app(SecurityAudit::class)->record($request, 'login.failed', 'INVALID_REQUEST');
        }

        return $response;
    }
}
