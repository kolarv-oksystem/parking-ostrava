<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAutoReleaseToken
{
    /**
     * Hostingový CRON umí volat jen GET URL. Token v query brání tomu,
     * aby náhodně nalezená adresa uvolnila parkoviště.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $configuredToken = (string) config('parking.auto_release_token', '');
        if ($configuredToken === '') {
            return response()->json([
                'message' => 'Token pro automatické uvolnění není nakonfigurovaný.',
            ], 503);
        }

        $providedToken = (string) ($request->query('token') ?? '');

        if ($providedToken === '' || !hash_equals($configuredToken, $providedToken)) {
            return response()->json([
                'message' => 'Neplatný token pro automatické uvolnění.',
            ], 401);
        }

        return $next($request);
    }
}
