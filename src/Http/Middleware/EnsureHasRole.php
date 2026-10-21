<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Signl\Valkyrie\Facades\Valkyrie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passes when the authenticated user holds any of the given roles.
 */
final class EnsureHasRole
{
    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof Model) {
            throw new AuthenticationException;
        }

        foreach ($roles as $role) {
            if (Valkyrie::holdsRole($user, $role)) {
                return $next($request);
            }
        }

        throw new AuthorizationException;
    }
}
