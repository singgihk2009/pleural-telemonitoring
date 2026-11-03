<?php

use App\Http\Middleware\AuditSecurityEvents;
use App\Http\Middleware\EnsureActivePrincipal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trimStrings(except: ['pin']);
        $middleware->append(AuditSecurityEvents::class);
        $middleware->alias(['principal.active' => EnsureActivePrincipal::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'pin']);
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*'));
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            if ($exception instanceof HttpResponseException) {
                $response = $exception->getResponse();
                $response->headers->set('Cache-Control', 'no-store');

                return $response;
            }
            if ($exception instanceof ValidationException) {
                return response()->json(['message' => 'Validation failed.', 'errors' => $exception->errors()], 422)->header('Cache-Control', 'no-store');
            }
            $status = match (true) {
                $exception instanceof AuthenticationException => 401,
                $exception instanceof AuthorizationException => 403,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                $exception instanceof ModelNotFoundException => 404,
                default => 500,
            };
            $message = Response::$statusTexts[$status] ?? 'Server Error';
            $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];

            return response()->json(['message' => $message.'.'], $status, $headers)->header('Cache-Control', 'no-store');
        });
    })->create();
