<?php

namespace App\Http\Middleware;

use Closure;


class Product
{

    /**
     * handle an incoming request
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // apply the following checks only in production environment ONLY - headers are only set when requests come via Apigee
        if (env('APP_ENV') == 'production')
        {
            // if there's no api product name
            if (!$request->header('X-API-Product-Name'))
            {
                // return missing api product name error
                return response()->error('Missing API Product Name', 400);
            }
        }

        // proceed
        return $next($request);
    }
}
