<?php

namespace App\Services\Members;

use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\Member;
use App\Models\MemberCrmProfile;
use App\Models\MemberLinkRequest;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MemberLinkService
{
    public function __construct(
        private readonly MobileProtectionService $mobile,
        private readonly MemberMembershipService $memberships,
    ) {}

    public function detectOrCreate(Request $request, Account $account, Site $site): ?MemberLinkRequest
    {
        $profile = $account->memberProfile;
        if (! $profile?->mobile_verified_at || ! $profile->mobile_ciphertext) {
            return null;
        }

        $normalized = $this->mobile->decrypt($profile->mobile_ciphertext);
        $evidenceHash = $this->mobile->hashForTenant($normalized, $site->tenant_id);
        $candidateProfile = MemberCrmProfile::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('mobile_hash', $evidenceHash)
            ->whereHas('member', fn ($query) => $query
                ->whereNull('account_id')
                ->whereHas('sites', fn ($sites) => $sites
                    ->whereKey($site->id)
                    ->where('member_sites.status', 'active')))
            ->with(['member.crmProfile'])
            ->first();
        if (! $candidateProfile) {
            return null;
        }

        if (MemberLinkRequest::query()
            ->where('lead_member_id', $candidateProfile->member_id)
            ->where('account_id', $account->id)
            ->where('status', 'separate_approved')
            ->exists()) {
            return null;
        }

        $activeKey = hash('sha256', "tenant:{$site->tenant_id}|lead:{$candidateProfile->member_id}");

        try {
            return DB::transaction(function () use ($request, $account, $site, $profile, $candidateProfile, $evidenceHash, $activeKey) {
                $existing = MemberLinkRequest::query()->where('active_key', $activeKey)->lockForUpdate()->first();
                if ($existing) {
                    if ($existing->expires_at?->isPast() && in_array($existing->status, ['pending_member_confirmation', 'pending_staff_review'], true)) {
                        $existing->update([
                            'status' => 'expired',
                            'active_key' => null,
                            'version' => $existing->version + 1,
                        ]);
                        $this->audit($request, $existing, 'member.link.expired', $account->id);
                        $existing = null;
                    }
                }
                if ($existing) {
                    abort_unless($existing->account_id === $account->id, 409, 'MEMBER_LINK_CANDIDATE_BUSY');

                    return $existing->load(['site', 'leadMember.crmProfile']);
                }

                $linkRequest = MemberLinkRequest::create([
                    'public_id' => (string) Str::ulid(),
                    'tenant_id' => $site->tenant_id,
                    'site_id' => $site->id,
                    'lead_member_id' => $candidateProfile->member_id,
                    'account_id' => $account->id,
                    'status' => 'pending_member_confirmation',
                    'evidence_type' => 'verified_mobile',
                    'evidence_hash' => $evidenceHash,
                    'member_profile_version' => $profile->version,
                    'expires_at' => now()->addDays(7),
                    'active_key' => $activeKey,
                    'request_id' => $request->attributes->get('request_id'),
                    'version' => 1,
                ]);
                $this->audit($request, $linkRequest, 'member.link.requested', $account->id);

                return $linkRequest->load(['site', 'leadMember.crmProfile']);
            });
        } catch (QueryException $exception) {
            $existing = MemberLinkRequest::query()->where('active_key', $activeKey)->first();
            if (! $existing) {
                throw $exception;
            }
            abort_unless($existing->account_id === $account->id, 409, 'MEMBER_LINK_CANDIDATE_BUSY');

            return $existing->load(['site', 'leadMember.crmProfile']);
        }
    }

    public function memberDecision(Request $request, Account $account, string $publicId, string $decision, int $version): MemberLinkRequest
    {
        return DB::transaction(function () use ($request, $account, $publicId, $decision, $version) {
            $linkRequest = MemberLinkRequest::query()
                ->where('public_id', $publicId)
                ->where('account_id', $account->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($linkRequest->status === 'pending_member_confirmation', 409, 'MEMBER_LINK_STATE_CONFLICT');
            abort_if($linkRequest->expires_at?->isPast(), 409, 'MEMBER_LINK_EXPIRED');
            abort_unless($linkRequest->version === $version, 409, 'MEMBER_LINK_VERSION_CONFLICT');

            if ($decision === 'link') {
                $this->assertEvidenceStillMatches($linkRequest, $account);
            }

            $linkRequest->update([
                'status' => 'pending_staff_review',
                'member_decision' => $decision,
                'member_decided_at' => now(),
                'version' => $linkRequest->version + 1,
            ]);
            $this->audit($request, $linkRequest, 'member.link.member_decided', $account->id, null, [
                'decision' => $decision,
            ]);

            return $linkRequest->fresh(['site', 'leadMember.crmProfile']);
        });
    }

    public function staffDecision(
        Request $request,
        Staff $staff,
        Site $site,
        string $publicId,
        string $decision,
        string $reason,
        int $version,
    ): MemberLinkRequest {
        return DB::transaction(function () use ($request, $staff, $site, $publicId, $decision, $reason, $version) {
            $linkRequest = MemberLinkRequest::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('site_id', $site->id)
                ->where('public_id', $publicId)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($linkRequest->status === 'pending_staff_review', 409, 'MEMBER_LINK_STATE_CONFLICT');
            abort_if($linkRequest->expires_at?->isPast(), 409, 'MEMBER_LINK_EXPIRED');
            abort_unless($linkRequest->version === $version, 409, 'MEMBER_LINK_VERSION_CONFLICT');
            abort_if($staff->account_id === $linkRequest->account_id, 403, 'MEMBER_LINK_SELF_REVIEW_FORBIDDEN');

            $account = Account::query()->whereKey($linkRequest->account_id)->lockForUpdate()->firstOrFail();
            $resolvedMember = null;
            $status = match ($decision) {
                'approve_link' => 'linked',
                'approve_separate' => 'separate_approved',
                default => 'rejected',
            };

            if ($decision === 'approve_link') {
                abort_unless($linkRequest->member_decision === 'link', 409, 'MEMBER_LINK_DECISION_MISMATCH');
                $this->assertEvidenceStillMatches($linkRequest, $account);
                $lead = Member::query()->whereKey($linkRequest->lead_member_id)->lockForUpdate()->firstOrFail();
                abort_unless($lead->tenant_id === $staff->tenant_id && $lead->account_id === null, 409, 'MEMBER_LINK_LEAD_CONFLICT');
                $existing = Member::query()
                    ->where('tenant_id', $staff->tenant_id)
                    ->where('account_id', $account->id)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    $linkRequest->update([
                        'status' => 'conflict',
                        'resolved_member_id' => $existing->id,
                        'reviewed_by_staff_id' => $staff->id,
                        'reviewed_at' => now(),
                        'review_reason' => $reason,
                        'version' => $linkRequest->version + 1,
                    ]);
                    $this->audit($request, $linkRequest, 'member.link.conflict', $staff->account_id, $staff->id, [
                        'existingMemberId' => $existing->id,
                        'subjectAccountId' => $account->id,
                    ]);

                    return $linkRequest->fresh(['site', 'leadMember.crmProfile', 'resolvedMember']);
                }

                $updated = Member::whereKey($lead->id)->whereNull('account_id')->update([
                    'account_id' => $account->id,
                    'version' => DB::raw('version + 1'),
                ]);
                abort_if($updated !== 1, 409, 'MEMBER_LINK_LEAD_CONFLICT');
                $lead->crmProfile()->update([
                    'mobile_source' => 'member_verified_link',
                    'mobile_verified_at' => now(),
                ]);
                $resolvedMember = $this->memberships->ensure($account, $site);
            } elseif ($decision === 'approve_separate') {
                abort_unless($linkRequest->member_decision === 'not_me', 409, 'MEMBER_LINK_DECISION_MISMATCH');
                $resolvedMember = $this->memberships->ensure($account, $site);
            }

            $linkRequest->update([
                'status' => $status,
                'resolved_member_id' => $resolvedMember?->id,
                'active_key' => $status === 'rejected' ? $linkRequest->active_key : null,
                'reviewed_by_staff_id' => $staff->id,
                'reviewed_at' => now(),
                'review_reason' => $reason,
                'version' => $linkRequest->version + 1,
            ]);
            $this->audit($request, $linkRequest, "member.link.{$status}", $staff->account_id, $staff->id, [
                'memberDecision' => $linkRequest->member_decision,
                'resolvedMemberId' => $resolvedMember?->id,
                'subjectAccountId' => $account->id,
            ]);

            return $linkRequest->fresh(['site', 'leadMember.crmProfile', 'resolvedMember']);
        });
    }

    public function memberData(MemberLinkRequest $request): array
    {
        $request->loadMissing(['site', 'leadMember.crmProfile']);

        return [
            'state' => 'link_review',
            'requestId' => $request->public_id,
            'status' => $request->status,
            'memberDecision' => $request->member_decision,
            'site' => $request->site?->only(['id', 'name', 'status']),
            'candidate' => [
                'nameMasked' => $this->maskName($request->leadMember?->crmProfile?->name),
                'mobileMasked' => $request->leadMember?->crmProfile?->mobile_last4
                    ? "*******{$request->leadMember->crmProfile->mobile_last4}"
                    : null,
                'status' => $request->leadMember?->status,
                'appAccessStatus' => $request->leadMember?->app_access_status,
            ],
            'version' => $request->version,
            'expiresAt' => $request->expires_at?->toISOString(),
        ];
    }

    public function staffData(MemberLinkRequest $request): array
    {
        $request->loadMissing(['site', 'leadMember.crmProfile', 'account.memberProfile', 'reviewer', 'resolvedMember']);

        return [
            'requestId' => $request->public_id,
            'status' => $request->status,
            'memberDecision' => $request->member_decision,
            'leadMember' => [
                'id' => $request->lead_member_id,
                'memberNo' => $request->leadMember?->member_no,
                'name' => $request->leadMember?->crmProfile?->name,
                'mobileMasked' => $request->leadMember?->crmProfile?->mobile_last4
                    ? "*******{$request->leadMember->crmProfile->mobile_last4}"
                    : null,
            ],
            'account' => [
                'displayName' => $request->account?->memberProfile?->display_name ?: $request->account?->display_name,
                'mobileMasked' => $request->account?->memberProfile?->mobile_last4
                    ? "*******{$request->account->memberProfile->mobile_last4}"
                    : null,
                'mobileVerified' => (bool) $request->account?->memberProfile?->mobile_verified_at,
            ],
            'resolvedMemberId' => $request->resolved_member_id,
            'reviewer' => $request->reviewer?->only(['id', 'name']),
            'reviewReason' => $request->review_reason,
            'createdAt' => $request->created_at?->toISOString(),
            'expiresAt' => $request->expires_at?->toISOString(),
            'version' => $request->version,
        ];
    }

    private function assertEvidenceStillMatches(MemberLinkRequest $linkRequest, Account $account): void
    {
        $profile = $account->memberProfile()->lockForUpdate()->first();
        abort_unless($profile?->mobile_verified_at && $profile->mobile_ciphertext, 409, 'MEMBER_LINK_EVIDENCE_EXPIRED');
        $normalized = $this->mobile->decrypt($profile->mobile_ciphertext);
        $hash = $this->mobile->hashForTenant($normalized, $linkRequest->tenant_id);
        abort_unless(hash_equals($linkRequest->evidence_hash, $hash), 409, 'MEMBER_LINK_EVIDENCE_EXPIRED');
        $crmProfile = MemberCrmProfile::where('member_id', $linkRequest->lead_member_id)
            ->where('tenant_id', $linkRequest->tenant_id)
            ->lockForUpdate()
            ->first();
        abort_unless($crmProfile?->mobile_hash && hash_equals($crmProfile->mobile_hash, $hash), 409, 'MEMBER_LINK_EVIDENCE_EXPIRED');
    }

    private function audit(
        Request $request,
        MemberLinkRequest $linkRequest,
        string $action,
        ?int $actorAccountId,
        ?int $actorStaffId = null,
        array $metadata = [],
    ): void {
        AuditEvent::create([
            'tenant_id' => $linkRequest->tenant_id,
            'site_id' => $linkRequest->site_id,
            'actor_account_id' => $actorAccountId,
            'actor_staff_id' => $actorStaffId,
            'action' => $action,
            'subject_type' => 'member_link_request',
            'subject_id' => $linkRequest->id,
            'request_id' => $request->attributes->get('request_id'),
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    private function maskName(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        return mb_substr($name, 0, 1).str_repeat('*', max(mb_strlen($name) - 1, 1));
    }
}
