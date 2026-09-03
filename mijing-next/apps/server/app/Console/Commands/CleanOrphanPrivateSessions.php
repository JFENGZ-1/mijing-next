<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Enums\CourseType;
use App\Enums\ScheduleSessionStatus;
use App\Models\ScheduleSession;
use Illuminate\Console\Command;

/**
 * 一次性清理：私教时间制孤儿 session。
 *
 * 历史 bug 残留：① 代约卡校验失败回滚前留下的 0 预约 session；
 * ② 预约全取消后仍 scheduled 的 session（占用教练看板时间段）。
 * 判定特征：私教 1v1（session_kind=private、capacity=1）、无教室（时间制动态创建不设 room）、
 * 课程为私教课、仍 scheduled、且不存在有效预约（confirmed/completed/waitlisted）。
 * 处理方式：标记 cancelled（软处理，取消的预约记录保留可查证），不物理删除。
 */
class CleanOrphanPrivateSessions extends Command
{
    protected $signature = 'sessions:clean-orphan-private {--force : 实际执行（默认 dry-run 仅列出）}';

    protected $description = '清理无有效预约的私教时间制孤儿 session（标记 cancelled 释放时间段）';

    public function handle(): int
    {
        $orphans = ScheduleSession::query()
            ->where('session_kind', 'private')
            ->where('capacity', 1)
            ->whereNull('room_id')
            ->where('status', ScheduleSessionStatus::Scheduled->value)
            ->whereHas('course', fn ($query) => $query->where('course_type', CourseType::Private))
            ->whereDoesntHave('appointments', fn ($query) => $query->whereIn('status', [
                AppointmentStatus::Confirmed,
                AppointmentStatus::Completed,
                AppointmentStatus::Waitlisted,
            ]))
            ->with(['course:id,name', 'coach:id,name'])
            ->withCount('appointments')
            ->orderBy('starts_at')
            ->get();

        if ($orphans->isEmpty()) {
            $this->info('没有需要清理的孤儿私教 session。');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', '教练', '课程', '开始时间', '预约数(均已取消)'],
            $orphans->map(fn (ScheduleSession $session) => [
                $session->id,
                $session->coach?->name ?? '-',
                $session->course?->name ?? '-',
                $session->starts_at?->format('Y-m-d H:i'),
                $session->appointments_count,
            ])->all(),
        );

        if (! $this->option('force')) {
            $this->warn("dry-run：共 {$orphans->count()} 条。确认后加 --force 实际执行。");

            return self::SUCCESS;
        }

        foreach ($orphans as $session) {
            $session->update([
                'status' => ScheduleSessionStatus::Cancelled,
                'version' => $session->version + 1,
            ]);
        }

        $this->info("已清理 {$orphans->count()} 条（标记 cancelled，预约记录保留）。");

        return self::SUCCESS;
    }
}
