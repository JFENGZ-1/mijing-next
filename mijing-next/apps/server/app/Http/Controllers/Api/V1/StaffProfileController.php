<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStaffProfileRequest;
use App\Http\Requests\UploadStaffAvatarRequest;
use App\Models\Staff;
use App\Services\Auth\StaffProfileService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StaffProfileController extends Controller
{
    public function show(Request $request, StaffProfileService $profiles)
    {
        return ApiResponse::success($profiles->read($this->staff($request)));
    }

    public function update(UpdateStaffProfileRequest $request, StaffProfileService $profiles)
    {
        return ApiResponse::success(
            $profiles->update($request, $this->staff($request), $request->validated()),
        );
    }

    public function uploadAvatar(UploadStaffAvatarRequest $request, StaffProfileService $profiles)
    {
        $staff = $this->staff($request);
        $extension = $request->file('avatar')->guessExtension() ?: 'jpg';
        $storedPath = $request->file('avatar')->storeAs(
            "staff-avatars/{$staff->account_id}",
            Str::uuid().'.'.$extension,
            'public',
        );

        return ApiResponse::success($profiles->storeAvatar($staff, $storedPath));
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
