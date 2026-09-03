<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\InteractsWithAdminBusinessScope;
use App\Http\Controllers\Controller;
use App\Models\CardProduct;
use App\Models\CompensationRole;
use App\Models\Course;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use App\Support\ApiResponse;

class AdminCardConsumptionOptionsController extends Controller
{
    use InteractsWithAdminBusinessScope;

    public function __invoke(Tenant $tenant, Site $site)
    {
        $site = $this->scopedSite($tenant, $site);

        $members = Member::query()
            ->where('tenant_id', $tenant->id)
            ->where(function ($query) use ($site) {
                $query->where('registration_site_id', $site->id)
                    ->orWhere('home_site_id', $site->id)
                    ->orWhereHas('sites', fn ($sites) => $sites->whereKey($site->id));
            })
            ->with(['crmProfile:id,member_id,name', 'account:id,display_name'])
            ->orderBy('member_no')
            ->limit(200)
            ->get();

        return ApiResponse::success([
            'cardProducts' => CardProduct::query()
                ->where('tenant_id', $tenant->id)
                ->where('site_id', $site->id)
                ->orderBy('name')
                ->limit(200)
                ->get()
                ->map(fn (CardProduct $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'code' => null,
                    'type' => $this->enumValue($product->card_type),
                    'status' => $this->enumValue($product->catalog_status),
                ])->values(),
            'courses' => Course::query()
                ->where('tenant_id', $tenant->id)
                ->where('site_id', $site->id)
                ->orderBy('name')
                ->limit(200)
                ->get()
                ->map(fn (Course $course) => [
                    'id' => $course->id,
                    'name' => $course->name,
                    'code' => null,
                    'type' => $this->enumValue($course->course_type),
                    'status' => $this->enumValue($course->catalog_status),
                ])->values(),
            'compensationRoles' => CompensationRole::query()
                ->where('tenant_id', $tenant->id)
                ->where('site_id', $site->id)
                ->orderBy('role_type')
                ->orderBy('name')
                ->limit(200)
                ->get()
                ->map(fn (CompensationRole $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'code' => $role->code,
                    'type' => $role->role_type,
                    'status' => $role->status,
                ])->values(),
            'staff' => Staff::query()
                ->where('tenant_id', $tenant->id)
                ->whereHas('sites', fn ($sites) => $sites->whereKey($site->id))
                ->orderBy('name')
                ->limit(200)
                ->get()
                ->map(fn (Staff $staff) => [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'code' => $staff->employee_no,
                    'type' => null,
                    'status' => $staff->status,
                ])->values(),
            'members' => $members->map(fn (Member $member) => [
                'id' => $member->id,
                'name' => $this->memberName($member),
                'code' => $member->member_no,
                'type' => null,
                'status' => $member->status,
            ])->values(),
            'memberCards' => MemberCard::query()
                ->where('tenant_id', $tenant->id)
                ->where('site_id', $site->id)
                ->with(['member.crmProfile:id,member_id,name', 'member.account:id,display_name'])
                ->orderByDesc('id')
                ->limit(200)
                ->get()
                ->map(fn (MemberCard $card) => [
                    'id' => $card->id,
                    'name' => $card->card_no.' · '.$this->memberName($card->member),
                    'code' => $card->card_no,
                    'type' => $this->enumValue($card->card_type),
                    'status' => $this->enumValue($card->status),
                ])->values(),
            'sessions' => ScheduleSession::query()
                ->where('tenant_id', $tenant->id)
                ->where('site_id', $site->id)
                ->where('starts_at', '>=', now()->subDays(30))
                ->with('course:id,name')
                ->orderByDesc('starts_at')
                ->limit(200)
                ->get()
                ->map(fn (ScheduleSession $session) => [
                    'id' => $session->id,
                    'name' => ($session->course?->name ?? '已删除课程').' · '.$session->starts_at?->format('Y-m-d H:i'),
                    'code' => '#'.$session->id,
                    'type' => $this->enumValue($session->session_kind),
                    'status' => $this->enumValue($session->status),
                    'version' => (int) $session->version,
                ])->values(),
        ]);
    }
}
