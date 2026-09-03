<?php

namespace App\Http\Middleware;

use App\Models\SuperAdmin;
use App\Models\SuperAdminAuditLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordSuperAdminAudit
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        /** @var SuperAdmin|null $admin */
        $admin = $request->user();

        try {
            $response = $next($request);
            $this->record($request, $admin, $response->getStatusCode());

            return $response;
        } catch (Throwable $exception) {
            $status = method_exists($exception, 'getStatusCode') ? (int) $exception->getStatusCode() : 500;
            $this->record($request, $admin, $status);
            throw $exception;
        }
    }

    private function record(Request $request, ?SuperAdmin $admin, int $status): void
    {
        try {
            $parameters = collect($request->route()?->parameters() ?? [])->map(function ($value, $key) {
                if ($value instanceof Model) {
                    return $key.':'.$value->getRouteKey();
                }

                return is_scalar($value) ? $key.':'.$value : null;
            })->filter()->values();

            SuperAdminAuditLog::query()->create([
                'super_admin_id' => $admin?->id,
                'action' => (string) ($request->route()?->getActionName() ?? 'unknown'),
                'method' => $request->method(),
                'path' => '/'.$request->path(),
                'subject' => $parameters->isEmpty() ? null : $parameters->join(', '),
                'request_id' => $request->attributes->get('request_id'),
                'status_code' => $status,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500) ?: null,
                'metadata' => ['routeName' => $request->route()?->getName()],
                'occurred_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('super admin audit write failed', [
                'requestId' => $request->attributes->get('request_id'),
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
