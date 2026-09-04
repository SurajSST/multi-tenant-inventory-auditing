<?php

use App\Http\Middleware\EnsureActive;
use App\Http\Middleware\EnsurePlatformOwner;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'platform' => EnsurePlatformOwner::class,
        ]);

        // Deactivating a member of staff, and forcing a first-login password
        // change, both have to bite on every page — not just the ones that
        // remember to ask.
        $middleware->appendToGroup('web', [
            // Makes Auth::logoutOtherDevices() genuinely end other sessions.
            AuthenticateSession::class,
            EnsureActive::class,
            ForcePasswordChange::class,
            ResolveTenant::class,
        ]);

        // ResolveTenant MUST run before SubstituteBindings so that route model
        // bindings (e.g. {demand}, {order}, {bill}) execute with TenantContext active.
        $middleware->priority([
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            PreventRequestForgery::class,
            Authenticate::class,
            AuthenticateSession::class,
            EnsureActive::class,
            ForcePasswordChange::class,
            ResolveTenant::class,
            SubstituteBindings::class,
            EnsureRole::class,
            EnsurePlatformOwner::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
