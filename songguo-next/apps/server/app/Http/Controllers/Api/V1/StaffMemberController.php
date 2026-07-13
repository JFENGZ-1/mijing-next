<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BatchImportStaffMembersRequest;
use App\Http\Requests\ChangeMemberAppAccessRequest;
use App\Http\Requests\PointAdjustMemberRequest;
use App\Http\Requests\StoreMemberNoteRequest;
use App\Http\Requests\StoreStaffMemberRequest;
use App\Http\Requests\SyncMemberTagsRequest;
use App\Http\Requests\TransitionMemberStatusRequest;
use App\Http\Requests\UpdateMemberStickyRemarkRequest;
use App\Http\Requests\UpdateStaffMemberRequest;
use App\Models\Member;
use App\Models\MemberCrmProfile;
use App\Models\MemberNote;
use App\Models\MemberTag;
use App\Models\Staff;
use App\Services\Members\MemberAuditService;
use App\Services\Members\MemberCrmFieldPolicyService;
use App\Services\Members\MobileProtectionService;
use App\Services\Members\StaffCrmBatchImportService;
use App\Services\Members\StaffCrmMemberArchiveService;
use App\Services\Members\StaffCrmMemberListService;
use App\Services\Members\StaffMemberAccessService;
use App\Services\Points\PointLedgerWriteService;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffMemberController extends Controller
{
    public function index(Request $request, int $site, StaffMemberAccessService $access, StaffCrmMemberListService $crmList)
    {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'crm.member.read', $siteModel->id);

        $query = $crmList->applyListFilters(
            $crmList->scopedQuery($staff, $siteModel)->with(['crmProfile', 'owner', 'tags']),
            $request,
            $staff,
            $siteModel,
        );

        $paginator = $query->orderByDesc('members.id')->paginate(min(max($request->integer('perPage', 20), 1), 50));

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(fn (Member $member) => $this->memberData($member)),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(
        StoreStaffMemberRequest $request,
        int $site,
        StaffMemberAccessService $access,
        MobileProtectionService $mobile,
        MemberAuditService $audit,
        MemberCrmFieldPolicyService $fieldPolicy,
    ) {
        $staff = $this->staff($request);
        $staff->loadMissing('tenant');
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'crm.member.create', $siteModel->id);
        $fieldPolicy->assertUpsertAllowed($staff->tenant, $request->all(), isCreate: true);

        try {
            $member = DB::transaction(function () use ($request, $staff, $siteModel, $mobile, $audit) {
                $contact = $this->contactAttributes($request->input('mobile'), $staff->tenant_id, $mobile);
                if ($contact['mobile_hash'] && MemberCrmProfile::where('tenant_id', $staff->tenant_id)->where('mobile_hash', $contact['mobile_hash'])->exists()) {
                    abort(409, 'CRM_MOBILE_CONFLICT');
                }
                $member = Member::create([
                    'tenant_id' => $staff->tenant_id,
                    'account_id' => null,
                    'member_no' => 'M'.strtoupper((string) Str::ulid()),
                    'status' => 'lead',
                    'app_access_status' => 'allowed',
                    'source' => 'staff-miniapp',
                    'registration_site_id' => $siteModel->id,
                    'home_site_id' => $siteModel->id,
                    'owner_staff_id' => $request->boolean('assignToMe', true) ? $staff->id : null,
                    'joined_at' => now(),
                    'status_changed_at' => now(),
                    'status_changed_by_staff_id' => $staff->id,
                ]);
                MemberCrmProfile::create([
                    'tenant_id' => $staff->tenant_id,
                    'member_id' => $member->id,
                    'name' => $request->string('name')->toString(),
                    'gender' => $request->input('gender'),
                    'birth_date' => $request->input('birthDate'),
                    ...$contact,
                ]);
                DB::table('member_sites')->insert([
                    'tenant_id' => $staff->tenant_id,
                    'member_id' => $member->id,
                    'site_id' => $siteModel->id,
                    'relationship_type' => 'registered',
                    'status' => 'active',
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('member_status_events')->insert([
                    'tenant_id' => $staff->tenant_id,
                    'member_id' => $member->id,
                    'from_status' => null,
                    'to_status' => 'lead',
                    'reason' => '员工创建潜客',
                    'site_id' => $siteModel->id,
                    'actor_staff_id' => $staff->id,
                    'request_id' => $request->attributes->get('request_id'),
                    'occurred_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $audit->record($request, $staff, $siteModel, $member, 'crm.member.created', ['source' => 'staff-miniapp']);

                return $member;
            });
        } catch (QueryException) {
            return ApiResponse::error('CRM_MOBILE_CONFLICT', '该手机号已存在于当前租户，不会自动合并会员', 409);
        }

        return ApiResponse::success($this->memberData($member->fresh(['crmProfile', 'owner', 'tags'])), 201);
    }

    public function show(Request $request, int $site, int $member, StaffMemberAccessService $access)
    {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'crm.member.read', $siteModel->id);
        $memberModel = $access->member($staff, $siteModel, $member)->load(['crmProfile', 'owner', 'tags']);

        return ApiResponse::success([
            ...$this->memberData($memberModel),
            'notesCount' => $memberModel->notes()->count(),
            'accountLinked' => $memberModel->account_id !== null,
        ]);
    }

    public function update(
        UpdateStaffMemberRequest $request,
        int $site,
        int $member,
        StaffMemberAccessService $access,
        MobileProtectionService $mobile,
        MemberAuditService $audit,
        MemberCrmFieldPolicyService $fieldPolicy,
    ) {
        $staff = $this->staff($request);
        $staff->loadMissing('tenant');
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'crm.member.update', $siteModel->id);
        $memberModel = $access->member($staff, $siteModel, $member)->load('crmProfile');
        $fieldPolicy->assertUpsertAllowed($staff->tenant, $request->all(), isCreate: false, member: $memberModel);

        try {
            DB::transaction(function () use ($request, $staff, $siteModel, $memberModel, $mobile, $audit) {
                $updated = Member::whereKey($memberModel->id)->where('version', $request->integer('version'))
                    ->update(['version' => DB::raw('version + 1')]);
                abort_if($updated !== 1, 409, 'MEMBER_VERSION_CONFLICT');

                $profile = $memberModel->crmProfile()->firstOrFail();
                $attributes = [];
                if ($request->has('name')) {
                    $attributes['name'] = $request->string('name')->toString();
                }
                if ($request->has('gender')) {
                    $attributes['gender'] = $request->input('gender');
                }
                if ($request->has('birthDate')) {
                    $attributes['birth_date'] = $request->input('birthDate');
                }
                if ($request->has('mobile')) {
                    $attributes = [...$attributes, ...$this->contactAttributes($request->input('mobile'), $staff->tenant_id, $mobile)];
                    if ($attributes['mobile_hash'] && MemberCrmProfile::where('tenant_id', $staff->tenant_id)
                        ->where('mobile_hash', $attributes['mobile_hash'])->whereKeyNot($profile->id)->exists()) {
                        abort(409, 'CRM_MOBILE_CONFLICT');
                    }
                }
                $profile->update([...$attributes, 'version' => $profile->version + 1]);
                $audit->record($request, $staff, $siteModel, $memberModel, 'crm.member.updated', ['fields' => array_keys($attributes)]);
            });
        } catch (QueryException) {
            return ApiResponse::error('CRM_MOBILE_CONFLICT', '该手机号已存在于当前租户，不会自动合并会员', 409);
        }

        return ApiResponse::success($this->memberData($memberModel->fresh(['crmProfile', 'owner', 'tags'])));
    }

    public function transitionStatus(
        TransitionMemberStatusRequest $request,
        int $site,
        int $member,
        StaffMemberAccessService $access,
        MemberAuditService $audit,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'crm.member.status.manage', $siteModel->id);
        $memberModel = $access->member($staff, $siteModel, $member);
        $target = $request->string('targetStatus')->toString();
        $allowed = ['lead:active', 'active:frozen', 'frozen:active'];
        abort_unless(in_array("{$memberModel->status}:{$target}", $allowed, true), 409, 'INVALID_MEMBER_STATUS_TRANSITION');

        DB::transaction(function () use ($request, $staff, $siteModel, $memberModel, $target, $audit) {
            $updated = Member::whereKey($memberModel->id)
                ->where('version', $request->integer('version'))
                ->where('status', $memberModel->status)
                ->update([
                    'status' => $target,
                    'status_changed_at' => now(),
                    'status_changed_by_staff_id' => $staff->id,
                    'version' => DB::raw('version + 1'),
                ]);
            abort_if($updated !== 1, 409, 'MEMBER_VERSION_CONFLICT');
            DB::table('member_status_events')->insert([
                'tenant_id' => $staff->tenant_id,
                'member_id' => $memberModel->id,
                'from_status' => $memberModel->status,
                'to_status' => $target,
                'reason' => $request->string('reason')->toString(),
                'site_id' => $siteModel->id,
                'actor_staff_id' => $staff->id,
                'request_id' => $request->attributes->get('request_id'),
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $audit->record($request, $staff, $siteModel, $memberModel, 'crm.member.status_changed', [
                'from' => $memberModel->status,
                'to' => $target,
            ]);
        });

        return ApiResponse::success($this->memberData($memberModel->fresh(['crmProfile', 'owner', 'tags'])));
    }

    public function claimOwner(Request $request, int $site, int $member, StaffMemberAccessService $access, MemberAuditService $audit)
    {
        $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'crm.member.owner.claim', $siteModel->id);
        $memberModel = $access->member($staff, $siteModel, $member);
        DB::transaction(function () use ($request, $staff, $siteModel, $memberModel, $audit) {
            $updated = Member::whereKey($memberModel->id)->whereNull('owner_staff_id')
                ->where('version', $request->integer('version'))
                ->update(['owner_staff_id' => $staff->id, 'version' => DB::raw('version + 1')]);
            abort_if($updated !== 1, 409, 'MEMBER_OWNER_CONFLICT');
            $audit->record($request, $staff, $siteModel, $memberModel, 'crm.member.owner_claimed');
        });

        return ApiResponse::success($this->memberData($memberModel->fresh(['crmProfile', 'owner', 'tags'])));
    }

    public function notes(Request $request, int $site, int $member, StaffMemberAccessService $access)
    {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'crm.member.note.read', $siteModel->id);
        $memberModel = $access->member($staff, $siteModel, $member);

        return ApiResponse::success($memberModel->notes()->with('author')->latest()->limit(100)->get()->map(fn (MemberNote $note) => [
            'id' => $note->id,
            'body' => $note->body,
            'correctionOfId' => $note->correction_of_id,
            'author' => $note->author?->name,
            'createdAt' => $note->created_at->toISOString(),
        ]));
    }

    public function addNote(StoreMemberNoteRequest $request, int $site, int $member, StaffMemberAccessService $access, MemberAuditService $audit)
    {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'crm.member.note.add', $siteModel->id);
        $memberModel = $access->member($staff, $siteModel, $member);
        if ($request->filled('correctionOfId')) {
            abort_unless($memberModel->notes()->whereKey($request->integer('correctionOfId'))->exists(), 422, 'INVALID_NOTE_CORRECTION');
        }
        $note = DB::transaction(function () use ($request, $staff, $siteModel, $memberModel, $audit) {
            $note = MemberNote::create([
                'tenant_id' => $staff->tenant_id,
                'member_id' => $memberModel->id,
                'site_id' => $siteModel->id,
                'author_staff_id' => $staff->id,
                'correction_of_id' => $request->input('correctionOfId'),
                'body' => $request->string('body')->toString(),
                'request_id' => $request->attributes->get('request_id'),
            ]);
            $audit->record($request, $staff, $siteModel, $memberModel, 'crm.member.note_added', ['noteId' => $note->id]);

            return $note;
        });

        return ApiResponse::success(['id' => $note->id, 'createdAt' => $note->created_at->toISOString()], 201);
    }

    public function syncTags(SyncMemberTagsRequest $request, int $site, int $member, StaffMemberAccessService $access, MemberAuditService $audit)
    {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'crm.member.tag.assign', $siteModel->id);
        $memberModel = $access->member($staff, $siteModel, $member);
        $tags = MemberTag::where('tenant_id', $staff->tenant_id)->where('status', 'active')->whereIn('id', $request->validated('tagIds'))->get();
        abort_if($tags->count() !== count($request->validated('tagIds')), 422, 'INVALID_MEMBER_TAG');

        DB::transaction(function () use ($request, $staff, $siteModel, $memberModel, $tags, $audit) {
            $updated = Member::whereKey($memberModel->id)->where('version', $request->integer('version'))
                ->update(['version' => DB::raw('version + 1')]);
            abort_if($updated !== 1, 409, 'MEMBER_VERSION_CONFLICT');
            $memberModel->tags()->sync($tags->mapWithKeys(fn (MemberTag $tag) => [$tag->id => [
                'tenant_id' => $staff->tenant_id,
                'assigned_by_staff_id' => $staff->id,
                'assigned_at' => now(),
            ]])->all());
            $audit->record($request, $staff, $siteModel, $memberModel, 'crm.member.tags_changed', ['tagIds' => $tags->pluck('id')->all()]);
        });

        return ApiResponse::success($this->memberData($memberModel->fresh(['crmProfile', 'owner', 'tags'])));
    }

    public function changeAppAccess(ChangeMemberAppAccessRequest $request, int $site, int $member, StaffMemberAccessService $access, MemberAuditService $audit)
    {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'crm.member.app_access.manage', $siteModel->id);
        $memberModel = $access->member($staff, $siteModel, $member);
        DB::transaction(function () use ($request, $staff, $siteModel, $memberModel, $audit) {
            $updated = Member::whereKey($memberModel->id)->where('version', $request->integer('version'))
                ->update(['app_access_status' => $request->string('status')->toString(), 'version' => DB::raw('version + 1')]);
            abort_if($updated !== 1, 409, 'MEMBER_VERSION_CONFLICT');
            $audit->record($request, $staff, $siteModel, $memberModel, 'crm.member.app_access_changed', [
                'from' => $memberModel->app_access_status,
                'status' => $request->string('status')->toString(),
                'reason' => $request->string('reason')->toString(),
            ]);
        });

        return ApiResponse::success($this->memberData($memberModel->fresh(['crmProfile', 'owner', 'tags'])));
    }

    public function batchImport(
        BatchImportStaffMembersRequest $request,
        int $site,
        StaffMemberAccessService $access,
        StaffCrmBatchImportService $importer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'crm.member.batch-import', $siteModel->id);

        return ApiResponse::success($importer->import(
            $staff,
            $siteModel,
            $request->validated(),
            (string) $request->attributes->get('request_id'),
        ));
    }

    public function deletedIndex(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        StaffCrmMemberArchiveService $archive,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'crm.member.deleted.read', $siteModel->id);

        return ApiResponse::success($archive->listDeleted(
            $staff,
            $siteModel,
            max($request->integer('page', 1), 1),
            min(max($request->integer('perPage', 20), 1), 50),
        ));
    }

    public function restore(
        Request $request,
        int $site,
        int $member,
        StaffMemberAccessService $access,
        StaffCrmMemberArchiveService $archive,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'crm.member.restore', $siteModel->id);
        $memberModel = $archive->archivedMember($staff, $siteModel, $member);
        $restored = $archive->restore($request, $staff, $siteModel, $memberModel);

        return ApiResponse::success($this->memberData($restored));
    }

    public function updateStickyRemark(
        UpdateMemberStickyRemarkRequest $request,
        int $site,
        int $member,
        StaffMemberAccessService $access,
        MemberAuditService $audit,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'crm.member.update', $siteModel->id);
        $memberModel = $access->member($staff, $siteModel, $member)->load('crmProfile');

        DB::transaction(function () use ($request, $staff, $siteModel, $memberModel, $audit) {
            $updated = Member::whereKey($memberModel->id)
                ->where('version', $request->integer('version'))
                ->update(['version' => DB::raw('version + 1')]);
            abort_if($updated !== 1, 409, 'MEMBER_VERSION_CONFLICT');

            $profile = $memberModel->crmProfile()->firstOrFail();
            $remark = $request->input('stickyRemark');
            $profile->update([
                'sticky_remark' => filled($remark) ? $request->string('stickyRemark')->toString() : null,
                'version' => $profile->version + 1,
            ]);
            $audit->record($request, $staff, $siteModel, $memberModel, 'crm.member.sticky_remark_updated', [
                'hasStickyRemark' => filled($remark),
            ]);
        });

        return ApiResponse::success($this->memberData($memberModel->fresh(['crmProfile', 'owner', 'tags'])));
    }

    public function pointAdjust(
        PointAdjustMemberRequest $request,
        int $site,
        int $member,
        StaffMemberAccessService $access,
        PointLedgerWriteService $points,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'points.adjust', $siteModel->id);
        $memberModel = $access->member($staff, $siteModel, $member);
        $memberModel->loadMissing('tenant');
        abort_unless($memberModel->tenant?->points_enabled, 404);

        $result = $points->adjust($staff, $memberModel, $request->validated());

        return ApiResponse::success([
            'ledgerEntryId' => $result['entry']->id,
            'totalPoint' => $result['balance'],
            'created' => $result['created'],
        ], $result['created'] ? 201 : 200);
    }

    private function contactAttributes(?string $value, int $tenantId, MobileProtectionService $mobile): array
    {
        if (blank($value)) {
            return ['mobile_ciphertext' => null, 'mobile_hash' => null, 'mobile_last4' => null, 'mobile_source' => null, 'mobile_verified_at' => null];
        }
        $normalized = $mobile->normalize($value);

        return [
            'mobile_ciphertext' => $mobile->encrypt($normalized),
            'mobile_hash' => $mobile->hashForTenant($normalized, $tenantId),
            'mobile_last4' => substr($normalized, -4),
            'mobile_source' => 'staff_entered',
            'mobile_verified_at' => null,
        ];
    }

    private function memberData(Member $member): array
    {
        return [
            'id' => $member->id,
            'memberNo' => $member->member_no,
            'name' => $member->crmProfile?->name,
            'mobileMasked' => $member->crmProfile?->mobile_last4 ? "*******{$member->crmProfile->mobile_last4}" : null,
            'mobileVerified' => (bool) $member->crmProfile?->mobile_verified_at,
            'gender' => $member->crmProfile?->gender,
            'birthDate' => $member->crmProfile?->birth_date?->format('Y-m-d'),
            'status' => $member->status,
            'appAccessStatus' => $member->app_access_status,
            'owner' => $member->owner ? ['id' => $member->owner->id, 'name' => $member->owner->name] : null,
            'tags' => $member->tags->map->only(['id', 'name', 'color']),
            'stickyRemark' => $member->crmProfile?->sticky_remark,
            'hasStickyRemark' => filled($member->crmProfile?->sticky_remark),
            'version' => $member->version,
            'joinedAt' => $member->joined_at?->toISOString(),
        ];
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
