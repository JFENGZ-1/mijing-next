<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberTagRequest;
use App\Models\MemberTag;
use App\Models\Staff;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MemberTagController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['siteId' => ['required', 'integer', 'min:1']]);
        $staff = $this->staff($request);
        abort_unless($staff->hasPermission('crm.member.read', $request->integer('siteId')), 403, 'PERMISSION_DENIED');

        return ApiResponse::success(MemberTag::where('tenant_id', $staff->tenant_id)->where('status', 'active')->orderBy('name')->get()->map->only(['id', 'name', 'color', 'status', 'version']));
    }

    public function store(StoreMemberTagRequest $request)
    {
        $staff = $this->staff($request);
        abort_unless($staff->hasPermission('crm.tag.manage'), 403, 'PERMISSION_DENIED');
        try {
            $tag = MemberTag::create([
                'tenant_id' => $staff->tenant_id,
                'name' => $request->string('name')->toString(),
                'normalized_name' => Str::lower(trim($request->string('name')->toString())),
                'color' => $request->string('color')->toString(),
            ]);
        } catch (QueryException) {
            return ApiResponse::error('MEMBER_TAG_EXISTS', '同名标签已经存在', 409);
        }

        return ApiResponse::success($tag->only(['id', 'name', 'color', 'status', 'version']), 201);
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
