<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The platform console. Above every school, and outside all of them — so this
 * deliberately sits apart from the role middleware, which only ever answers
 * questions about a posting at one school.
 */
class EnsurePlatformOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->isPlatformOwner(),
            403,
            'The platform console is not part of any one school.',
        );

        return $next($request);
    }
}
