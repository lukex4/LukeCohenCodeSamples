<?php

namespace App\Http\Middleware;

use Closure;

/**
*
* Applies CORS-compliant HTTP headers to every response
*
*/
class CorsCompliant {

  public function handle($request, Closure $next) {

  //Intercepts OPTIONS requests
    if($request->isMethod('OPTIONS')) {
        $response = response('', 200);
    } else {
        // Pass the request to the next middleware
        $response = $next($request);
    }

    // $response = $next($request);

    /** Add headers to the response to make it CORS-compliant */
    $response->header('Access-Cors-Compliant',            'true');
    $response->header('Access-Control-Allow-Origin',      '*');
    $response->header('Access-Control-Allow-Methods',     'OPTIONS, HEAD, GET, POST, PUT, PATCH, DELETE');
    $response->header('Access-Control-Allow-Credentials', 'true');
    $response->header('Access-Control-Allow-Headers',     'Content-Type, Depth, User-Agent, X-File-Size, X-Requested-With, If-Modified-Since, X-File-Name, Cache-Control, Access-Control-Request-Method, Access-Control-Request-Headers, Accept, Authorization');
    $response->header('Access-Control-Max-Age',           '86400');

    return $response;

  }

}

?>
