<?php

use App\Http\Middleware\CaptureCampaignParameters;
use App\Http\Middleware\GuardUnconfiguredEmailWorkflows;
use App\Http\Middleware\RecordAccountActivity;
use App\Http\Middleware\RecordRequestDiagnostics;
use App\Http\Middleware\RequireActiveAccount;
use App\Http\Middleware\RequireSensitiveActionAssurance;
use App\Http\Middleware\RequireSensitivePasswordConfirmation;
use App\Http\Middleware\RequireWaymarkInstallation;
use App\Http\Middleware\ResolvePublicRedirects;
use App\Http\Middleware\ServeWaymarkMaintenance;
use App\Http\Middleware\TriggerNonCriticalFallback;
use App\Http\Responses\PublicNotFoundResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend([RecordRequestDiagnostics::class, RequireWaymarkInstallation::class, ServeWaymarkMaintenance::class, ResolvePublicRedirects::class]);
        $middleware->web(append: [GuardUnconfiguredEmailWorkflows::class, CaptureCampaignParameters::class, RecordAccountActivity::class, RequireActiveAccount::class, TriggerNonCriticalFallback::class]);
        $middleware->alias([
            'sensitive.confirmed' => RequireSensitiveActionAssurance::class,
            'sensitive.password-confirmed' => RequireSensitivePasswordConfirmation::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(fn (NotFoundHttpException $exception, Request $request) => $request->expectsJson() ? null : app(PublicNotFoundResponse::class)->make($request));
        $exceptions->render(fn (ModelNotFoundException $exception, Request $request) => $request->expectsJson() ? null : app(PublicNotFoundResponse::class)->make($request));
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
