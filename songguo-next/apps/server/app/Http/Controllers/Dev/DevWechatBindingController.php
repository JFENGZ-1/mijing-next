<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\WechatIdentity;
use App\Services\Auth\StaffWechatBindingService;
use App\Services\Wechat\WechatAuthService;
use App\Support\Dev\MaskedOpenid;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class DevWechatBindingController extends Controller
{
    public function index(): View
    {
        $staffAppid = config('wechat.apps.staff.appid');
        $memberAppid = config('wechat.apps.member.appid');

        $tenants = Tenant::query()
            ->with(['sites' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        $staff = Staff::query()
            ->with(['tenant', 'sites', 'account.wechatIdentities'])
            ->orderBy('tenant_id')
            ->orderBy('employee_no')
            ->get();

        $members = Member::query()
            ->with(['tenant', 'account.wechatIdentities'])
            ->orderBy('tenant_id')
            ->orderBy('member_no')
            ->get();

        return view('dev.wechat-bindings', [
            'tenants' => $tenants,
            'staff' => $staff,
            'members' => $members,
            'staffAppid' => is_string($staffAppid) ? $staffAppid : null,
            'memberAppid' => is_string($memberAppid) ? $memberAppid : null,
            'maskOpenid' => MaskedOpenid::format(...),
        ]);
    }

    public function bindStaff(
        Request $request,
        StaffWechatBindingService $binding,
        WechatAuthService $wechat,
    ): RedirectResponse {
        $validated = $request->validate([
            'employee_no' => ['required', 'string', 'max:64'],
            'openid' => ['nullable', 'string', 'max:64', 'required_without:code'],
            'code' => ['nullable', 'string', 'max:512', 'required_without:openid'],
        ]);

        $staff = Staff::query()->where('employee_no', $validated['employee_no'])->first();
        if (! $staff) {
            return back()->with('error', '未找到该员工工号。');
        }

        $appid = config('wechat.apps.staff.appid');
        if (! is_string($appid) || $appid === '') {
            return back()->with('error', '员工端微信 AppID 未配置。');
        }

        try {
            if (! empty($validated['code'])) {
                $session = $wechat->exchangeCode('staff', $validated['code']);
                $binding->bindSessionToStaff($session, $staff);
                $masked = MaskedOpenid::format($session->openid);
            } else {
                $binding->bindOpenidToStaff($appid, $validated['openid'], $staff);
                $masked = MaskedOpenid::format($validated['openid']);
            }
        } catch (RuntimeException $exception) {
            return back()->with('error', match ($exception->getMessage()) {
                'WECHAT_NOT_CONFIGURED' => '员工端微信应用尚未配置。',
                'WECHAT_UNAVAILABLE' => '微信服务暂时不可用。',
                default => '微信登录凭证无效或已过期。',
            });
        }

        return back()->with('success', "员工 {$staff->employee_no} 已绑定微信（openid 末四位 {$masked}）。");
    }

    public function bindMember(
        Request $request,
        StaffWechatBindingService $binding,
        WechatAuthService $wechat,
    ): RedirectResponse {
        $validated = $request->validate([
            'member_no' => ['required', 'string', 'max:64'],
            'openid' => ['nullable', 'string', 'max:64', 'required_without:code'],
            'code' => ['nullable', 'string', 'max:512', 'required_without:openid'],
        ]);

        $member = Member::query()->where('member_no', $validated['member_no'])->first();
        if (! $member) {
            return back()->with('error', '未找到该会员编号。');
        }

        $appid = config('wechat.apps.member.appid');
        if (! is_string($appid) || $appid === '') {
            return back()->with('error', '会员端微信 AppID 未配置。');
        }

        try {
            if (! empty($validated['code'])) {
                $session = $wechat->exchangeCode('member', $validated['code']);
                $binding->bindSessionToMember($session, $member);
                $masked = MaskedOpenid::format($session->openid);
            } else {
                $binding->bindOpenidToMember($appid, $validated['openid'], $member);
                $masked = MaskedOpenid::format($validated['openid']);
            }
        } catch (RuntimeException $exception) {
            return back()->with('error', match ($exception->getMessage()) {
                'WECHAT_NOT_CONFIGURED' => '会员端微信应用尚未配置。',
                'WECHAT_UNAVAILABLE' => '微信服务暂时不可用。',
                default => '微信登录凭证无效或已过期。',
            });
        }

        return back()->with('success', "会员 {$member->member_no} 已绑定微信（openid 末四位 {$masked}）。");
    }

    public function unbind(
        Request $request,
        StaffWechatBindingService $binding,
    ): RedirectResponse {
        $validated = $request->validate([
            'identity_id' => ['required', 'integer', 'exists:wechat_identities,id'],
        ]);

        $identity = WechatIdentity::query()->findOrFail($validated['identity_id']);
        $masked = MaskedOpenid::format($identity->openid);
        $binding->unbindIdentity($identity);

        return back()->with('success', "已解除微信绑定（openid 末四位 {$masked}）。");
    }
}
