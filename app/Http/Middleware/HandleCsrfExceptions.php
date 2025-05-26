<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Session\TokenMismatchException;

class HandleCsrfExceptions
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (TokenMismatchException $e) {
            // For API requests, return a JSON response
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'CSRF token mismatch. Please refresh and try again.',
                    'code' => 'token_mismatch'
                ], 419);
            }
            
            // For web requests, redirect back with an error
            return redirect()->back()->with('error', 'Session expired. Please try again.');
        }
    }
} 