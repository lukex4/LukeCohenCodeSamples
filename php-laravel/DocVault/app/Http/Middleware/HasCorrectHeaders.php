<?php

namespace App\Http\Middleware;

use Closure;

/**
*
* Checks POST, PUT, PATCH, DELETE queries for
*
*/
class HasCorrectHeaders {

    public function handle($request, Closure $next) {

        /** If this isn't a relevant Request, move on */
        $curMethod = $request->method();

        if ($curMethod == 'POST') {

            /** Check if content-type header is set, and what its value is */
            $contentType    = '';

            if (isset($request->headers->all()['content-type'])) {
                $contentType    = $request->headers->all()['content-type'][0];
            }

            if ($contentType !== 'application/json') {
                return response()->json(array(
                    'error' => 'POST, PUT, PATCH & DELETE requests must include \'Content-Type: application/json\' header'
                ), 418);
            }

        }

        return $next($request);

    }

}

?>
