<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level permission guard. Use as:
 *
 *   Route::middleware('permission:roles.manage')->group(...);
 *   Route::middleware('permission:users.manage|roles.manage')->group(...);
 *
 * Multiple permissions separated by `|` are treated as OR; the user
 * passes as long as they hold *any* of them. Super Admin bypasses via
 * the Gate::before hook so this middleware never blocks them.
 *
 * Falls back to the Spatie `permission` middleware name so we stay
 * interoperable with the package's docs / examples, but with our own
 * (lighter) implementation that's friendly to JSON APIs.
 */
final class EnsurePermission
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException();
        }

        $needed = array_filter(array_map('trim', explode('|', $permissions)));

        foreach ($needed as $permission) {
            if ($user->can($permission)) {
                return $next($request);
            }
        }

        throw new AuthorizationException(
            'You do not have permission to perform this action.'
        );
    }
}
