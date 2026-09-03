<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMemberContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user();
        abort_unless($account && $account->status === 'active', 403, 'ACCOUNT_DISABLED');
        abort_unless($account->tokenCan('client:member'), 403, 'TOKEN_AUDIENCE_INVALID');

        return $next($request);
    }
}
