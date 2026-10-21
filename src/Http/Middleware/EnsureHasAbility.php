<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passes when the authenticated user can perform any of the given abilities.
 */
final class EnsureHasAbility
{
    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();

        if (! $user instanceof Authorizable) {
            throw new AuthenticationException;
        }

        foreach ($abilities as $ability) {
            if ($user->can($ability)) {
                return $next($request);
            }
        }

        throw new AuthorizationException;
    }
}
