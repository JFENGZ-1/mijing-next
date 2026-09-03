<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\LegalConsent;
use App\Models\LegalDocument;
use App\Models\Member;
use App\Models\MemberCrmProfile;
use App\Models\MemberLinkRequest;
use App\Models\Site;
use App\Services\Members\MobileProtectionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MemberLinkRequestSeeder extends Seeder
{
    public function run(MobileProtectionService $mobile): void
    {
        if (! in_array(config('app.env'), ['local', 'testing'], true)) {
            return;
        }

        $memberAccount = Account::query()->find(2);
        $leadMember = Member::query()
            ->where('tenant_id', 1)
            ->whereNull('account_id')
            ->where('status', 'lead')
            ->first();
        $site = Site::query()->find(1);
        $profile = $memberAccount?->memberProfile;

        if (! $memberAccount || ! $leadMember || ! $site || ! $profile?->mobile_verified_at || ! $profile->mobile_ciphertext) {
            $this->command?->warn('MemberLinkRequestSeeder skipped: required local fixtures missing.');

            return;
        }

        $existing = MemberLinkRequest::query()
            ->where('account_id', $memberAccount->id)
            ->where('lead_member_id', $leadMember->id)
            ->where('status', 'pending_staff_review')
            ->first();

        $normalized = $mobile->decrypt($profile->mobile_ciphertext);
        $evidenceHash = $mobile->hashForTenant($normalized, $site->tenant_id);
        $activeKey = hash('sha256', "tenant:{$site->tenant_id}|lead:{$leadMember->id}");

        DB::transaction(function () use ($mobile, $memberAccount, $leadMember, $site, $profile, $normalized, $evidenceHash, $activeKey, $existing) {
            $profile->forceFill([
                'display_name' => $profile->display_name ?: '本地演示会员',
            ])->save();

            $requiredDocuments = LegalDocument::query()
                ->where('scope_key', 'global')
                ->where('status', 'published')
                ->where('is_required', true)
                ->pluck('id');
            foreach ($requiredDocuments as $documentId) {
                LegalConsent::query()->firstOrCreate(
                    [
                        'account_id' => $memberAccount->id,
                        'legal_document_id' => $documentId,
                        'action' => 'accepted',
                    ],
                    ['source' => 'local_seed', 'occurred_at' => now()],
                );
            }

            $crmProfile = MemberCrmProfile::query()->firstOrCreate(
                ['tenant_id' => $site->tenant_id, 'member_id' => $leadMember->id],
                ['name' => '本地审核演示潜客', 'version' => 1],
            );
            $crmProfile->forceFill([
                'name' => $crmProfile->name ?: '本地审核演示潜客',
                'mobile_ciphertext' => $mobile->encrypt($normalized),
                'mobile_hash' => $evidenceHash,
                'mobile_last4' => $profile->mobile_last4,
                'mobile_source' => 'staff_entered',
            ])->save();

            if ($existing) {
                return;
            }

            MemberLinkRequest::create([
                'public_id' => (string) Str::ulid(),
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'lead_member_id' => $leadMember->id,
                'account_id' => $memberAccount->id,
                'status' => 'pending_staff_review',
                'member_decision' => 'link',
                'evidence_type' => 'verified_mobile',
                'evidence_hash' => $evidenceHash,
                'member_profile_version' => $profile->version,
                'active_key' => $activeKey,
                'member_decided_at' => now(),
                'expires_at' => now()->addDays(7),
                'version' => 1,
            ]);
        });

        if ($existing) {
            $this->command?->info("MemberLinkRequestSeeder: pending review {$existing->public_id} already exists; profile fixtures refreshed.");

            return;
        }

        $this->command?->info('MemberLinkRequestSeeder: pending_staff_review link request created.');
    }
}
