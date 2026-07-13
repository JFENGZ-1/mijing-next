<?php

namespace App\Http\Middleware;

use App\Models\Staff;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user();
        abort_unless($account && $account->status === 'active', 403, 'ACCOUNT_DISABLED');
        abort_unless($account->tokenCan('client:staff'), 403, 'TOKEN_AUDIENCE_INVALID');

        $staff = Staff::query()
            ->where('account_id', $account->id)
            ->where('status', 'active')
            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
            ->whereHas('sites')
            ->get()
            ->first(fn (Staff $profile) => $account->tokenCan("staff:{$profile->id}"));

        abort_unless($staff, 403, 'STAFF_ACCESS_DENIED');
        abort_unless($account->tokenCan("tenant:{$staff->tenant_id}"), 403, 'TOKEN_AUDIENCE_INVALID');

        $request->attributes->set('staff_context', $staff);

        return $next($request);
    }
}
