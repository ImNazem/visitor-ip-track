<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    public function handle($request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->handlePreflightRequest();
        }

        if ($request->isMethod('options')) {
            return response('', 200)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }

        $response = $next($request);

        return $this->addCorsHeaders($response);
    }

    private function handlePreflightRequest()
    {
        $resp = response('', 200);
        $resp->headers->set('Access-Control-Allow-Origin', '*');
        $resp->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH');
        $resp->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-HTTP-Method-Override');
        $resp->headers->set('Access-Control-Max-Age', '86400');
        // Leave credentials off when using '*' (browser requirement)
        return $resp;
    }

    private function addCorsHeaders($response)
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-HTTP-Method-Override');
        $response->headers->set('Access-Control-Max-Age', '86400');
        return $response;
    }
}
