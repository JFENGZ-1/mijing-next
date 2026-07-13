<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $now = now();

        DB::table('permissions')->upsert([
            ['name' => '查看场馆', 'code' => 'organization.site.read', 'module' => 'organization', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '管理场馆', 'code' => 'organization.site.manage', 'module' => 'organization', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看员工', 'code' => 'access.staff.read', 'module' => 'access', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '管理员工', 'code' => 'access.staff.manage', 'module' => 'access', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '管理角色权限', 'code' => 'access.role.manage', 'module' => 'access', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看会员', 'code' => 'crm.member.read', 'module' => 'crm', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '搜索会员手机号', 'code' => 'crm.member.mobile.search', 'module' => 'crm', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '创建会员潜客', 'code' => 'crm.member.create', 'module' => 'crm', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '编辑会员档案', 'code' => 'crm.member.update', 'module' => 'crm', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '管理会员状态', 'code' => 'crm.member.status.manage', 'module' => 'crm', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '认领会员顾问', 'code' => 'crm.member.owner.claim', 'module' => 'crm', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看会员备注', 'code' => 'crm.member.note.read', 'module' => 'crm', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '新增会员备注', 'code' => 'crm.member.note.add', 'module' => 'crm', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '分配会员标签', 'code' => 'crm.member.tag.assign', 'module' => 'crm', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '管理会员标签', 'code' => 'crm.tag.manage', 'module' => 'crm', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '管理会员端访问', 'code' => 'crm.member.app_access.manage', 'module' => 'crm', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '审核会员账号关联', 'code' => 'crm.member.link.review', 'module' => 'crm', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '批量导入会员潜客', 'code' => 'crm.member.batch-import', 'module' => 'crm', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看已删除会员', 'code' => 'crm.member.deleted.read', 'module' => 'crm', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '恢复已删除会员', 'code' => 'crm.member.restore', 'module' => 'crm', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看卡种目录', 'code' => 'card-product.catalog.read', 'module' => 'card', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '编辑卡种模板', 'code' => 'card-product.editor.write', 'module' => 'card', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '归档卡种模板', 'code' => 'card-product.archive', 'module' => 'card', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '会员开卡', 'code' => 'member-card.issue', 'module' => 'card', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看会员卡', 'code' => 'member-card.read', 'module' => 'card', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '调整储值余额', 'code' => 'member-card.balance.adjust', 'module' => 'card', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '调整计次次数', 'code' => 'member-card.count.adjust', 'module' => 'card', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '冻结/解冻会员卡', 'code' => 'member-card.freeze', 'module' => 'card', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '会员卡请假', 'code' => 'member-card.holiday.manage', 'module' => 'card', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '延长会员卡有效期', 'code' => 'member-card.validity.extend', 'module' => 'card', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '归档会员卡', 'code' => 'member-card.archive', 'module' => 'card', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看会员卡提醒', 'code' => 'member-card.reminder.read', 'module' => 'card', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '配置会员卡提醒', 'code' => 'member-card.reminder.config', 'module' => 'card', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看会员订单', 'code' => 'order.read', 'module' => 'order', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '订单金额更正', 'code' => 'order.amount.correct', 'module' => 'order', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '作废订单', 'code' => 'order.void', 'module' => 'order', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看课程目录', 'code' => 'course-catalog.read', 'module' => 'catalog', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '编辑课程目录', 'code' => 'course-catalog.write', 'module' => 'catalog', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看教室', 'code' => 'site.rooms.read', 'module' => 'organization', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '管理教室', 'code' => 'site.rooms.write', 'module' => 'organization', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看排课', 'code' => 'schedule.session.read', 'module' => 'schedule', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '管理排课', 'code' => 'schedule.session.write', 'module' => 'schedule', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '批量复制课表', 'code' => 'schedule.batch.copy', 'module' => 'schedule', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '批量停课', 'code' => 'schedule.batch.suspend', 'module' => 'schedule', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '批量取消排课', 'code' => 'schedule.batch.cancel', 'module' => 'schedule', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看预约规则', 'code' => 'booking.policy.read', 'module' => 'booking', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '配置预约规则', 'code' => 'booking.policy.write', 'module' => 'booking', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看课程日看板', 'code' => 'booking.staff-daily-board.read', 'module' => 'booking', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '创建预约', 'code' => 'booking.appointment.create', 'module' => 'booking', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '取消预约', 'code' => 'booking.appointment.cancel', 'module' => 'booking', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '排队转正', 'code' => 'booking.waitlist.promote', 'module' => 'booking', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '标记旷课', 'code' => 'booking.fulfillment.absent', 'module' => 'booking', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '会员签到', 'code' => 'booking.fulfillment.check-in', 'module' => 'booking', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '编辑预约备注', 'code' => 'booking.fulfillment.notes', 'module' => 'booking', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '改约私教课', 'code' => 'booking.appointment.reschedule', 'module' => 'booking', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '生成课程分享链接', 'code' => 'booking.share.create', 'module' => 'booking', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看会员预约记录', 'code' => 'booking.member-history.list', 'module' => 'booking', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看教练待上课表', 'code' => 'booking.staff-upcoming.read', 'module' => 'booking', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '调整会员积分', 'code' => 'points.adjust', 'module' => 'points', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看员工首页', 'code' => 'staff.dashboard.read', 'module' => 'identity', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看报表首页', 'code' => 'report.dashboard.read', 'module' => 'reporting', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看财务报表', 'code' => 'report.finance.read', 'module' => 'reporting', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看课程报表', 'code' => 'report.course.read', 'module' => 'reporting', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看排行榜', 'code' => 'report.rankings.read', 'module' => 'reporting', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看教练报表', 'code' => 'report.coach.read', 'module' => 'reporting', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看工资配置', 'code' => 'payroll.config.read', 'module' => 'payroll', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '编辑工资配置', 'code' => 'payroll.config.write', 'module' => 'payroll', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看工资报表', 'code' => 'payroll.report.read', 'module' => 'payroll', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '执行工资重算', 'code' => 'payroll.recompute.execute', 'module' => 'payroll', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看会员提醒', 'code' => 'notification.reminder.read', 'module' => 'notification', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '创建会员导出', 'code' => 'export.member.create', 'module' => 'export', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看导出任务', 'code' => 'export.job.read', 'module' => 'export', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看平台订阅', 'code' => 'platform.subscription.read', 'module' => 'platform', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看场馆资料', 'code' => 'site.profile.read', 'module' => 'organization', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '编辑场馆资料', 'code' => 'site.profile.write', 'module' => 'organization', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看员工目录', 'code' => 'staff.directory.read', 'module' => 'identity', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '管理员工目录', 'code' => 'staff.directory.write', 'module' => 'identity', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '员工离职', 'code' => 'staff.departure.soft', 'module' => 'identity', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '转让场馆所有权', 'code' => 'staff.directory.transfer-ownership', 'module' => 'identity', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看场馆设置', 'code' => 'tenant.settings.read', 'module' => 'tenant-config', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看默认配置', 'code' => 'tenant.settings.defaults.read', 'module' => 'tenant-config', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看会员端配置', 'code' => 'tenant.settings.member-experience.read', 'module' => 'tenant-config', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '读取会员端体验配置', 'code' => 'tenant.member-experience.read', 'module' => 'tenant-config', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '编辑会员端体验配置', 'code' => 'tenant.member-experience.write', 'module' => 'tenant-config', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看店长工具', 'code' => 'tenant.settings.operations.read', 'module' => 'tenant-config', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看连锁配置', 'code' => 'tenant.settings.chain.read', 'module' => 'tenant-config', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看连锁报表', 'code' => 'org.chain.read', 'module' => 'org', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看服务中心', 'code' => 'tenant.settings.support.read', 'module' => 'tenant-config', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '配置会员资料字段', 'code' => 'tenant.crm.field-config.write', 'module' => 'tenant-config', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看会员协议', 'code' => 'tenant.legal.membership-agreement.read', 'module' => 'tenant-config', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '编辑会员协议', 'code' => 'tenant.legal.membership-agreement.write', 'module' => 'tenant-config', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看闭店日历', 'code' => 'tenant.site.closure-calendar.read', 'module' => 'tenant-config', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '编辑闭店日历', 'code' => 'tenant.site.closure-calendar.write', 'module' => 'tenant-config', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看教练请假', 'code' => 'tenant.staff.vacation.read', 'module' => 'tenant-config', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '编辑教练请假', 'code' => 'tenant.staff.vacation.write', 'module' => 'tenant-config', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看提醒设置', 'code' => 'notification.channel.config.read', 'module' => 'notification', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '编辑提醒设置', 'code' => 'notification.channel.config.write', 'module' => 'notification', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '查看场馆公告', 'code' => 'notice.announcement.read', 'module' => 'notification', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '编辑场馆公告', 'code' => 'notice.announcement.write', 'module' => 'notification', 'created_at' => $now, 'updated_at' => $now],
        ], ['code'], ['name', 'module', 'updated_at']);

        $this->call(MemberLinkRequestSeeder::class);
        $this->call(CardProductSeeder::class);
        $this->call(CourseCatalogSeeder::class);
        $this->call(ScheduleSessionSeeder::class);
        $this->call(BookingPolicySeeder::class);
        $this->call(MemberCardSeeder::class);
        $this->call(MemberCardOrderSeeder::class);
        $this->call(NoticeSeeder::class);
        $this->call(CarouselSeeder::class);
        $this->call(PointLedgerSeeder::class);
        $this->call(OfficialAccountFollowSeeder::class);
    }
}
