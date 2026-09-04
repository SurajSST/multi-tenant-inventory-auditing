<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware('role:PURCHASE_OFFICER,SUPER_ADMIN') */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        $wanted = collect($roles)->map(fn ($r) => Role::from($r))->all();

        abort_unless($user && $user->hasAnyRole($wanted), 403,
            'This part of the system is restricted to: '.
            collect($wanted)->map(fn (Role $r) => $r->label())->join(', ', ' or ').'.'
        );

        return $next($request);
    }
}
