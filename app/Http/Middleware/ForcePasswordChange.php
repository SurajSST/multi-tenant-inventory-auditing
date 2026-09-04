<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every seeded and every reset account starts with a shared default password.
 * Until it is changed, the only page the user can reach is the one that
 * changes it — nothing is done in this system under a password somebody else
 * knows.
 */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $exempt = $request->routeIs('password.change', 'password.update', 'logout');

        if ($user && $user->must_reset_password && ! $exempt) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
