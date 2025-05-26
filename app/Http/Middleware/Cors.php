<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Cors
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
        // Handle preflight OPTIONS request
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 200);
        } else {
        $response = $next($request);
        }
        
        // Add CORS headers to response
        $response->headers->set('Access-Control-Allow-Origin', 'http://localhost:8081');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-Requested-With, Application, Cache-Control, Pragma');
        $response->headers->set('Access-Control-Max-Age', '86400'); // 24 hours
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        
        return $response;
    }
} 