<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Course;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCardOrder;
use App\Models\ScheduleSession;
use App\Models\Staff;
use App\Support\ApiResponse;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminResourceController extends Controller
{
    private const RESOURCES = ['staff', 'courses', 'schedules', 'appointments', 'cards', 'orders'];

    public function index(Request $request, string $resource)
    {
        abort_unless(in_array($resource, self::RESOURCES, true), 404);

        $filters = $request->validate([
            'tenantId' => ['sometimes', 'nullable', 'integer', 'exists:tenants,id'],
            'siteId' => ['sometimes', 'nullable', 'integer', 'exists:sites,id'],
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = match ($resource) {
            'staff' => $this->staff($filters),
            'courses' => $this->courses($filters),
            'schedules' => $this->schedules($filters),
            'appointments' => $this->appointments($filters),
            'cards' => $this->cards($filters),
            'orders' => $this->orders($filters),
        };

        return ApiResponse::success([
            'resource' => $resource,
            'items' => collect($paginator->items())->values(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }

    private function staff(array $filters): LengthAwarePaginator
    {
        $query = Staff::query()->with(['tenant:id,name,code', 'sites:id,name', 'roles:id,name']);
        $this->scope($query, $filters, hasSiteColumn: false);

        $query
            ->when($filters['siteId'] ?? null, fn (Builder $query, int $siteId) => $query
                ->whereHas('sites', fn (Builder $sites) => $sites->whereKey($siteId)))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['query'] ?? null, fn (Builder $query, string $keyword) => $query
                ->where(fn (Builder $nested) => $nested
                    ->where('name', 'like', "%{$keyword}%")
                    ->orWhere('employee_no', 'like', "%{$keyword}%")));

        return $query->latest('id')->paginate($filters['perPage'] ?? 20)->through(fn (Staff $staff) => [
            'id' => $staff->id,
            'name' => $staff->name,
            'employeeNo' => $staff->employee_no,
            'role' => $staff->roles->pluck('name')->filter()->implode('、') ?: '未分配角色',
            'site' => $staff->sites->pluck('name')->filter()->implode('、') ?: '未分配场馆',
            'lastActive' => $staff->joined_on?->toDateString() ?? '—',
            'status' => $staff->status,
            'tenant' => $staff->tenant?->name ?? '—',
        ]);
    }

    private function courses(array $filters): LengthAwarePaginator
    {
        $query = Course::query()->with(['tenant:id,name,code', 'site:id,name', 'coach:id,name']);
        $this->scope($query, $filters);

        $query
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('catalog_status', $status))
            ->when($filters['query'] ?? null, fn (Builder $query, string $keyword) => $query
                ->where('name', 'like', "%{$keyword}%"));

        return $query->latest('id')->paginate($filters['perPage'] ?? 20)->through(fn (Course $course) => [
            'id' => $course->id,
            'name' => $course->name,
            'type' => $this->enumValue($course->course_type),
            'duration' => $course->duration_minutes.' 分钟',
            'coaches' => $course->coach?->name ?? '未指定',
            'updatedAt' => $course->updated_at?->toISOString(),
            'status' => $this->enumValue($course->catalog_status),
            'tenant' => $course->tenant?->name ?? '—',
            'site' => $course->site?->name ?? '—',
        ]);
    }

    private function schedules(array $filters): LengthAwarePaginator
    {
        $query = ScheduleSession::query()->with([
            'tenant:id,name,code', 'site:id,name', 'course:id,name', 'coach:id,name', 'room:id,name',
        ]);
        $this->scope($query, $filters);

        $query
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['query'] ?? null, fn (Builder $query, string $keyword) => $query
                ->where(fn (Builder $nested) => $nested
                    ->whereHas('course', fn (Builder $course) => $course->where('name', 'like', "%{$keyword}%"))
                    ->orWhereHas('coach', fn (Builder $coach) => $coach->where('name', 'like', "%{$keyword}%"))))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('starts_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('starts_at', '<=', $to));

        return $query->latest('starts_at')->paginate($filters['perPage'] ?? 20)->through(fn (ScheduleSession $session) => [
            'id' => $session->id,
            'time' => $session->starts_at?->toISOString(),
            'course' => $session->course?->name ?? '已删除课程',
            'coach' => $session->coach?->name ?? '未指定',
            'room' => $session->room?->name ?? '未指定',
            'capacity' => $session->booked_count.' / '.$session->capacity,
            'status' => $this->enumValue($session->status),
            'tenant' => $session->tenant?->name ?? '—',
            'site' => $session->site?->name ?? '—',
        ]);
    }

    private function appointments(array $filters): LengthAwarePaginator
    {
        $query = Appointment::query()->with([
            'tenant:id,name,code', 'site:id,name', 'session.course:id,name',
            'member:id,account_id,member_no', 'member.account:id,display_name', 'member.crmProfile:id,member_id,name',
            'memberCard:id,card_no',
        ]);
        $this->scope($query, $filters);

        $query
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['query'] ?? null, function (Builder $query, string $keyword) {
                $query->whereHas('member', fn (Builder $member) => $member
                    ->where('member_no', 'like', "%{$keyword}%")
                    ->orWhereHas('crmProfile', fn (Builder $profile) => $profile->where('name', 'like', "%{$keyword}%")));
            })
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('booked_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('booked_at', '<=', $to));

        return $query->latest('booked_at')->paginate($filters['perPage'] ?? 20)->through(fn (Appointment $appointment) => [
            'id' => $appointment->id,
            'member' => $this->memberName($appointment->member),
            'session' => $appointment->session?->course?->name ?? '已删除场次',
            'bookedAt' => $appointment->booked_at?->toISOString(),
            'card' => $appointment->memberCard?->card_no ?? '未使用会员卡',
            'status' => $this->enumValue($appointment->status),
            'tenant' => $appointment->tenant?->name ?? '—',
            'site' => $appointment->site?->name ?? '—',
        ]);
    }

    private function cards(array $filters): LengthAwarePaginator
    {
        $query = MemberCard::query()->with([
            'tenant:id,name,code', 'site:id,name', 'cardProduct:id,name',
            'member:id,account_id,member_no', 'member.account:id,display_name', 'member.crmProfile:id,member_id,name',
        ]);
        $this->scope($query, $filters);

        $query
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['query'] ?? null, fn (Builder $query, string $keyword) => $query
                ->where(fn (Builder $nested) => $nested
                    ->where('card_no', 'like', "%{$keyword}%")
                    ->orWhereHas('member.crmProfile', fn (Builder $profile) => $profile->where('name', 'like', "%{$keyword}%"))));

        return $query->latest('id')->paginate($filters['perPage'] ?? 20)->through(fn (MemberCard $card) => [
            'id' => $card->id,
            'member' => $this->memberName($card->member),
            'product' => $card->cardProduct?->name ?? ($card->product_snapshot['name'] ?? '历史卡产品'),
            'balance' => $this->cardBalance($card),
            'expires' => $card->valid_until?->toDateString() ?? '长期有效',
            'status' => $this->enumValue($card->status),
            'cardNo' => $card->card_no,
            'tenant' => $card->tenant?->name ?? '—',
            'site' => $card->site?->name ?? '—',
        ]);
    }

    private function orders(array $filters): LengthAwarePaginator
    {
        $query = MemberCardOrder::query()->with([
            'member:id,account_id,member_no', 'member.account:id,display_name', 'member.crmProfile:id,member_id,name',
            'memberCard:id,card_product_id,product_snapshot', 'memberCard.cardProduct:id,name',
        ]);
        $this->scope($query, $filters);

        $query
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['query'] ?? null, fn (Builder $query, string $keyword) => $query
                ->where(fn (Builder $nested) => $nested
                    ->where('order_no', 'like', "%{$keyword}%")
                    ->orWhereHas('member.crmProfile', fn (Builder $profile) => $profile->where('name', 'like', "%{$keyword}%"))))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to));

        return $query->latest('id')->paginate($filters['perPage'] ?? 20)->through(fn (MemberCardOrder $order) => [
            'id' => $order->id,
            'orderNo' => $order->order_no,
            'member' => $this->memberName($order->member),
            'item' => $order->memberCard?->cardProduct?->name
                ?? ($order->memberCard?->product_snapshot['name'] ?? '会员卡订单'),
            'amount' => (float) $order->amount,
            'createdAt' => $order->created_at?->toISOString(),
            'status' => $this->enumValue($order->status),
            'tenantId' => $order->tenant_id,
            'siteId' => $order->site_id,
        ]);
    }

    private function scope(Builder $query, array $filters, bool $hasSiteColumn = true): void
    {
        $query->when($filters['tenantId'] ?? null, fn (Builder $query, int $tenantId) => $query->where('tenant_id', $tenantId));
        if ($hasSiteColumn) {
            $query->when($filters['siteId'] ?? null, fn (Builder $query, int $siteId) => $query->where('site_id', $siteId));
        }
    }

    private function memberName(?Member $member): string
    {
        return $member?->crmProfile?->name ?? $member?->account?->display_name ?? $member?->member_no ?? '未知会员';
    }

    private function cardBalance(MemberCard $card): string
    {
        return match ($this->enumValue($card->card_type)) {
            'stored_value' => '¥ '.number_format((float) ($card->cached_balance ?? 0), 2),
            'count' => ($card->cached_remaining_count ?? 0).' 次',
            default => $card->valid_until ? '有效至 '.$card->valid_until->toDateString() : '有效期权益',
        };
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }
}
