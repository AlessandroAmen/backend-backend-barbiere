<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SkipCsrfToken
{
    /**
     * Le URI che devono essere escluse dalla verifica CSRF.
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/appointments',
        'api/csrf-cookie',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        foreach ($this->except as $except) {
            if ($request->is($except)) {
                $request->headers->set('X-CSRF-TOKEN', csrf_token());
                return $next($request);
            }
        }

        // Consenti sempre le richieste API
        if ($request->is('api/*')) {
            return $next($request);
        }

        return $next($request);
    }

    /**
     * Determine if the request has a URI that should pass through CSRF verification.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function inExceptArray($request)
    {
        foreach ($this->except as $except) {
            if ($request->is($except)) {
                return true;
            }
        }

        return false;
    }
}
