<?php

namespace App\Http\Middleware;

use Closure;

class Gateway
{
    /**
     * handle an incoming request
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // apply the following checks only in production environment ONLY - headers are only set when requests come via Apigee
        if (env('APP_ENV') == 'production') {
            // if there's no gateway key
            if (!$request->header('X-Gateway-Key')) {
                // return missing gateway key error
                return response()->error('Missing Gateway Key', 400);
            }

            // if the gateway key provided doesn't match
            if ($request->header('X-Gateway-Key') !== env('X_GATEWAY_KEY')) {
                // return unauth'd gateway error
                return response()->error('Unauthorized Gateway', 401);
            }
        }

        // proceed
        return $next($request);
    }
}


