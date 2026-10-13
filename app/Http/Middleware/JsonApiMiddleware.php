<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JsonApiMiddleware
{
    const PARSED_METHODS = [
        'POST', 'PUT', 'PATCH',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->getMethod(), self::PARSED_METHODS, true)) {
            $content = $request->getContent();
            if ($content !== '') {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $request->merge($decoded);
                }
            }
        }

        return $next($request);
    }
}
