<?php

namespace App\Services\Auth;

use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StaffProfileService
{
    public function read(Staff $staff): array
    {
        $staff->loadMissing('account');
        $account = $staff->account;
        $mobile = $account->mobile;

        return [
            'id' => $staff->id,
            'displayName' => $staff->name,
            'employeeNo' => $staff->employee_no,
            'mobile' => $mobile,
            'mobileMasked' => $mobile ? '*******'.substr($mobile, -4) : null,
            'avatarUrl' => $account->avatar_url,
            'tenantId' => $staff->tenant_id,
            'version' => $staff->version,
        ];
    }

    /**
     * @param  array{displayName?: string, avatarUrl?: string|null, version?: int}  $payload
     */
    public function update(Request $request, Staff $staff, array $payload): array
    {
        if (array_key_exists('version', $payload) && (int) $payload['version'] !== $staff->version) {
            abort(409, 'VERSION_CONFLICT');
        }

        DB::transaction(function () use ($staff, $payload) {
            $staff->loadMissing('account');
            $account = $staff->account;

            if (array_key_exists('displayName', $payload)) {
                $staff->update([
                    'name' => $payload['displayName'],
                    'version' => $staff->version + 1,
                ]);
                $account->update(['display_name' => $payload['displayName']]);
            }

            if (array_key_exists('avatarUrl', $payload)) {
                $account->update(['avatar_url' => $payload['avatarUrl']]);
            }
        });

        return $this->read($staff->fresh(['account']));
    }

    public function storeAvatar(Staff $staff, string $storedPath): array
    {
        $staff->loadMissing('account');
        $avatarUrl = Storage::disk('public')->url($storedPath);
        $staff->account->update(['avatar_url' => $avatarUrl]);

        return [
            'avatarUrl' => $avatarUrl,
            'version' => $staff->fresh()->version,
        ];
    }
}
