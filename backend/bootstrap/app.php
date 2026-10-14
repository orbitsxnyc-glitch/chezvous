<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\RequireTwoFactor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias(['two-factor' => RequireTwoFactor::class]);
        // Un compte suspendu perd l'accès immédiatement, même avec une session ou un jeton déjà ouverts.
        $middleware->appendToGroup('api', EnsureAccountActive::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Erreurs normalisées : code, message, errors par champ.
        $exceptions->render(fn (ValidationException $e, Request $r) => $r->is('api/*')
            ? response()->json(['code' => 'validation_error', 'message' => $e->getMessage(), 'errors' => $e->errors()], 422) : null);
        $exceptions->render(fn (AuthenticationException $e, Request $r) => $r->is('api/*')
            ? response()->json(['code' => 'unauthenticated', 'message' => 'Authentification requise.'], 401) : null);
        $exceptions->render(fn (AuthorizationException|AccessDeniedHttpException $e, Request $r) => $r->is('api/*')
            ? response()->json(['code' => 'forbidden', 'message' => 'Action non autorisée.'], 403) : null);
        $exceptions->render(fn (NotFoundHttpException $e, Request $r) => $r->is('api/*')
            ? response()->json(['code' => 'not_found', 'message' => 'Ressource introuvable.'], 404) : null);
        $exceptions->render(fn (ThrottleRequestsException $e, Request $r) => $r->is('api/*')
            ? response()->json(['code' => 'too_many_requests', 'message' => 'Trop de tentatives, réessayez plus tard.'], 429, $e->getHeaders()) : null);
    })->create();
