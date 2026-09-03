<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDevToolsAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array(config('app.env'), ['local', 'testing'], true)) {
            return $next($request);
        }

        $token = config('app.dev_tools_token');
        if (! is_string($token) || $token === '') {
            abort(404);
        }

        $provided = $request->query('token', $request->header('X-Dev-Tools-Token', ''));
        if (! hash_equals($token, (string) $provided)) {
            abort(404);
        }

        return $next($request);
    }
}
