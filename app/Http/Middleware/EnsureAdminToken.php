<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdminToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $configuredToken = (string) config('parking.admin_token', '');
        if ($configuredToken === '') {
            return response()->json([
                'message' => 'Admin token není nakonfigurovaný.',
            ], 503);
        }

        $providedToken = (string) (
            $request->query('token')
            ?? $request->header('X-Admin-Token')
            ?? $request->bearerToken()
        );

        if (!hash_equals($configuredToken, $providedToken)) {
            return response()->json([
                'message' => 'Neplatný admin token.',
            ], 401);
        }

        return $next($request);
    }
}
