<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMemberProfileRequest;
use App\Http\Requests\UpdateMemberTenantProfileRequest;
use App\Http\Requests\UploadMemberAvatarRequest;
use App\Http\Requests\VerifyMemberMobileRequest;
use App\Models\LegalConsent;
use App\Models\LegalDocument;
use App\Models\MemberProfile;
use App\Services\Booking\MemberBookingAccessService;
use App\Services\Members\MemberPurchaseGateService;
use App\Services\Members\MemberRegistrationService;
use App\Services\Members\MemberTenantProfileService;
use App\Services\Members\MonthlyRankingService;
use App\Services\Members\MobileProtectionService;
use App\Services\Wechat\WechatPhoneService;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class MemberProfileController extends Controller
{
    public function show(Request $request, MemberRegistrationService $registration)
    {
        return ApiResponse::success($registration->status($request->user()));
    }

    public function readProfile(
        Request $request,
        MemberBookingAccessService $access,
        MemberTenantProfileService $profiles,
    ) {
        abort_unless($request->filled('tenantId'), 422, 'TENANT_ID_REQUIRED');

        $member = $access->member($request->user(), $request->integer('tenantId'));

        return ApiResponse::success($profiles->read($request->user(), $member));
    }

    public function purchaseGate(
        Request $request,
        MemberBookingAccessService $access,
        MemberPurchaseGateService $gate,
    ) {
        abort_unless($request->filled('tenantId'), 422, 'TENANT_ID_REQUIRED');

        $member = $access->member($request->user(), $request->integer('tenantId'));

        return ApiResponse::success($gate->evaluate($request->user(), $member));
    }

    public function updateRankingOptIn(
        Request $request,
        MemberBookingAccessService $access,
        MonthlyRankingService $ranking,
    ) {
        $validated = $request->validate([
            'tenantId' => ['required', 'integer', 'min:1'],
            'optIn' => ['required', 'boolean'],
        ]);

        $member = $access->member($request->user(), (int) $validated['tenantId']);
        $member->loadMissing('tenant');
        $ranking->assertEnabled($member->tenant);

        $member->update(['ranking_opt_in' => $validated['optIn']]);

        return ApiResponse::success([
            'tenantId' => $member->tenant_id,
            'rankingOptIn' => (bool) $member->ranking_opt_in,
        ]);
    }

    public function update(
        Request $request,
        MemberRegistrationService $registration,
        MemberBookingAccessService $access,
        MemberTenantProfileService $profiles,
    ) {
        if ($request->filled('tenantId')) {
            return $this->updateTenantProfile($request, $access, $profiles);
        }

        $onboardingRequest = UpdateMemberProfileRequest::createFrom($request);
        $onboardingRequest->setContainer(app())->validateResolved();

        return $this->updateOnboarding($onboardingRequest, $registration);
    }

    private function updateTenantProfile(
        Request $request,
        MemberBookingAccessService $access,
        MemberTenantProfileService $profiles,
    ) {
        $tenantRequest = UpdateMemberTenantProfileRequest::createFrom($request);
        $tenantRequest->setContainer(app())->validateResolved();
        $member = $access->member($request->user(), $request->integer('tenantId'));

        return ApiResponse::success(
            $profiles->update($request, $request->user(), $member, $tenantRequest->validated()),
        );
    }

    public function uploadAvatar(
        UploadMemberAvatarRequest $request,
        MemberBookingAccessService $access,
        MemberTenantProfileService $profiles,
    ) {
        abort_unless($request->filled('tenantId'), 422, 'TENANT_ID_REQUIRED');

        $member = $access->member($request->user(), $request->integer('tenantId'));
        $account = $request->user();
        $extension = $request->file('avatar')->guessExtension() ?: 'jpg';
        $storedPath = $request->file('avatar')->storeAs(
            "member-avatars/{$account->id}",
            Str::uuid().'.'.$extension,
            'public',
        );

        return ApiResponse::success($profiles->storeAvatar($request, $account, $member, $storedPath));
    }

    public function verifyMobile(
        VerifyMemberMobileRequest $request,
        WechatPhoneService $wechatPhone,
        MobileProtectionService $mobileProtection,
        MemberRegistrationService $registration,
    ) {
        // SMS OTP path is not wired here; see MemberSmsVerificationService + SmsGateway for future endpoints.
        try {
            $normalized = $mobileProtection->normalize($wechatPhone->exchangeCode($request->string('code')->toString()));
        } catch (RuntimeException $exception) {
            return match ($exception->getMessage()) {
                'WECHAT_NOT_CONFIGURED' => ApiResponse::error('WECHAT_NOT_CONFIGURED', '微信应用尚未配置', 503),
                'WECHAT_PHONE_UNAVAILABLE' => ApiResponse::error('WECHAT_PHONE_UNAVAILABLE', '微信手机号服务暂时不可用', 503),
                default => ApiResponse::error('WECHAT_PHONE_CODE_INVALID', '手机号凭证无效或已过期', 422),
            };
        }

        $account = $request->user();
        $mobileHash = $mobileProtection->hash($normalized);
        if (MemberProfile::where('mobile_hash', $mobileHash)->where('account_id', '!=', $account->id)->exists()) {
            return ApiResponse::error('MOBILE_ALREADY_BOUND', '该手机号已绑定其他账号，不会自动合并', 409);
        }

        try {
            DB::transaction(function () use ($request, $account, $normalized, $mobileHash, $mobileProtection) {
                $profile = MemberProfile::where('account_id', $account->id)->first();
                $attributes = [
                    'mobile_ciphertext' => $mobileProtection->encrypt($normalized),
                    'mobile_hash' => $mobileHash,
                    'mobile_last4' => substr($normalized, -4),
                    'mobile_verified_at' => now(),
                    'mobile_verification_method' => 'wechat',
                ];
                if (! $profile) {
                    MemberProfile::create([...$attributes, 'account_id' => $account->id]);

                    return;
                }

                abort_unless($request->filled('version'), 422, 'PROFILE_VERSION_REQUIRED');
                $updated = MemberProfile::whereKey($profile->id)
                    ->where('version', $request->integer('version'))
                    ->update([...$attributes, 'version' => DB::raw('version + 1')]);
                abort_if($updated !== 1, 409, 'PROFILE_VERSION_CONFLICT');
            });
        } catch (QueryException) {
            if (MemberProfile::where('account_id', $account->id)->exists()) {
                return ApiResponse::error('PROFILE_VERSION_CONFLICT', '资料已被其他设备修改，请刷新后重试', 409);
            }

            return ApiResponse::error('MOBILE_ALREADY_BOUND', '该手机号已绑定其他账号，不会自动合并', 409);
        }

        $account->unsetRelation('memberProfile');

        return ApiResponse::success($registration->status($account->fresh()));
    }

    private function updateOnboarding(UpdateMemberProfileRequest $request, MemberRegistrationService $registration)
    {
        $account = $request->user();
        $requiredDocuments = LegalDocument::query()
            ->where('scope_key', 'global')->where('status', 'published')->where('is_required', true)->get();
        abort_if($requiredDocuments->isEmpty(), 503, 'LEGAL_DOCUMENTS_NOT_CONFIGURED');

        $acceptedIds = collect($request->validated('acceptedDocumentIds'));
        abort_if($requiredDocuments->pluck('id')->diff($acceptedIds)->isNotEmpty(), 422, 'REQUIRED_CONSENT_MISSING');

        DB::transaction(function () use ($request, $account, $requiredDocuments) {
            $attributes = [
                'display_name' => $request->string('displayName')->toString(),
                'avatar_object_key' => $request->input('avatarObjectKey'),
                'gender' => $request->input('gender'),
                'birth_date' => $request->input('birthDate'),
                'height_cm' => $request->input('heightCm'),
                'weight_kg' => $request->input('weightKg'),
            ];
            $profile = MemberProfile::where('account_id', $account->id)->first();
            if ($profile) {
                abort_unless($request->filled('version'), 422, 'PROFILE_VERSION_REQUIRED');
                $updated = MemberProfile::query()
                    ->whereKey($profile->id)
                    ->where('version', $request->integer('version'))
                    ->update([...$attributes, 'version' => DB::raw('version + 1')]);
                abort_if($updated !== 1, 409, 'PROFILE_VERSION_CONFLICT');
            } else {
                MemberProfile::create([...$attributes, 'account_id' => $account->id]);
            }

            $account->update(['display_name' => $attributes['display_name']]);
            foreach ($requiredDocuments as $document) {
                LegalConsent::firstOrCreate(
                    ['account_id' => $account->id, 'legal_document_id' => $document->id, 'action' => 'accepted'],
                    [
                        'source' => 'member-miniapp',
                        'request_id' => $request->attributes->get('request_id'),
                        'ip_hash' => $this->requestHash($request->ip()),
                        'user_agent_hash' => $this->requestHash($request->userAgent()),
                        'occurred_at' => now(),
                    ],
                );
            }
        });

        $account->unsetRelation('memberProfile');

        return ApiResponse::success($registration->status($account->fresh()));
    }

    private function requestHash(?string $value): ?string
    {
        return $value ? hash_hmac('sha256', $value, (string) config('app.key')) : null;
    }
}
