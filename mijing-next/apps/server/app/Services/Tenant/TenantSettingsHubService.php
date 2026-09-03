<?php

namespace App\Services\Tenant;

use App\Models\CardProduct;
use App\Models\CardProductCourseScope;
use App\Models\Course;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;

class TenantSettingsHubService
{
    /**
     * @return array{
     *     featureFlags: array<string, bool>,
     *     setupCounts: array<string, int>,
     *     sections: list<array{
     *         key: string,
     *         label: string,
     *         legacyFlag: string,
     *         visible: bool,
     *         items: list<array{
     *             key: string,
     *             label: string,
     *             description: ?string,
     *             route: ?string,
     *             capability: string,
     *             requiredPermission: string,
     *             enabled: bool,
     *             implemented: bool,
     *             setupIncomplete: bool
     *         }>
     *     }>
     * }
     */
    public function hub(Staff $staff, Site $site): array
    {
        $siteId = $site->id;
        $setupCounts = $this->setupCounts($site);
        $featureFlags = $this->featureFlags($staff, $siteId);

        $sections = [
            $this->onboardingSection($staff, $siteId, $setupCounts, $featureFlags['shopBasics']),
            $this->basicsSection($staff, $siteId, $setupCounts, $featureFlags['shopBasics']),
            $this->defaultSection($staff, $siteId, $featureFlags['shopDefault']),
            $this->memberExperienceSection($staff, $siteId, $featureFlags['shopMemberConfig']),
            $this->operationsSection($staff, $siteId, $featureFlags['shopManagerTool']),
            $this->chainSection($staff, $siteId, $featureFlags['multipleShopConfig']),
            $this->supportSection($staff, $siteId, $featureFlags['shopServiceCenter']),
        ];

        return [
            'featureFlags' => $featureFlags,
            'setupCounts' => $setupCounts,
            'sections' => array_values(array_filter($sections, fn (array $section) => $section['visible'])),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function setupCounts(Site $site): array
    {
        $cardProductIds = CardProduct::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('site_id', $site->id)
            ->pluck('id');

        return [
            'siteProfile' => filled($site->name) && filled($site->address) ? 1 : 0,
            'cardProducts' => $cardProductIds->count() > 0 ? 1 : 0,
            'staffDirectory' => $site->staff()->count() > 0 ? 1 : 0,
            'courseCatalog' => Course::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('site_id', $site->id)
                ->exists() ? 1 : 0,
            'cardCourseLinks' => CardProductCourseScope::query()
                ->whereIn('card_product_id', $cardProductIds)
                ->exists() ? 1 : 0,
            'scheduleSessions' => ScheduleSession::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('site_id', $site->id)
                ->exists() ? 1 : 0,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function featureFlags(Staff $staff, int $siteId): array
    {
        $canReadHub = $staff->hasPermission('tenant.settings.read', $siteId);

        return [
            'shopBasics' => $canReadHub,
            'shopDefault' => $canReadHub && $staff->hasPermission('tenant.settings.defaults.read', $siteId),
            'shopMemberConfig' => $canReadHub && $staff->hasPermission('tenant.settings.member-experience.read', $siteId),
            'shopManagerTool' => $canReadHub && $staff->hasPermission('tenant.settings.operations.read', $siteId),
            'multipleShopConfig' => $canReadHub && $staff->hasPermission('tenant.settings.chain.read', $siteId),
            'shopServiceCenter' => $canReadHub && $staff->hasPermission('tenant.settings.support.read', $siteId),
        ];
    }

    /**
     * @param  array<string, int>  $setupCounts
     * @return array<string, mixed>
     */
    private function onboardingSection(Staff $staff, int $siteId, array $setupCounts, bool $visible): array
    {
        $items = [
            $this->item('site-profile', '场馆信息', '填写场馆名称与地址', '/pages/settings/site/index', 'site.profile.form', 'site.profile.read', $staff, $siteId, true, $setupCounts['siteProfile'] === 0),
            $this->item('room-catalog', '教室管理', '维护场馆教室与容纳人数', '/pages/settings/rooms/index', 'site.room.list', 'site.rooms.read', $staff, $siteId, true, false),
            $this->item('card-products', '会员卡', '创建和管理售卖的会员卡母卡', '/pages/settings/card-products/index', 'card-product.catalog', 'card-product.catalog.read', $staff, $siteId, true, $setupCounts['cardProducts'] === 0),
            $this->item('staff-directory', '教练/员工', '添加教练与员工，并设置操作权限', '/pages/settings/staff/index', 'staff.directory.list', 'staff.directory.read', $staff, $siteId, true, $setupCounts['staffDirectory'] === 0),
            $this->item('course-catalog', '课程库', '创建如阿斯汤伽、垫上普拉提等课目', '/pages/settings/courses/index', 'course-catalog.read', 'course-catalog.read', $staff, $siteId, true, $setupCounts['courseCatalog'] === 0),
            $this->item('card-course-links', '卡・课关联', '哪些卡可以预约哪些课，并设置课时费', '/pages/settings/card-products/course-matrix', 'card-product.course-scope', 'card-product.editor.write', $staff, $siteId, true, $setupCounts['cardCourseLinks'] === 0),
            $this->item('schedule-sessions', '排课/课程', '按日期进行排课管理', '/pages/course/timetable/index', 'schedule.session.read', 'schedule.session.read', $staff, $siteId, true, $setupCounts['scheduleSessions'] === 0),
        ];

        $incomplete = array_values(array_filter($items, fn (array $item) => $item['setupIncomplete'] && $item['enabled']));

        return [
            'key' => 'onboarding',
            'label' => '完善基础设置',
            'legacyFlag' => 'shop_basics',
            'visible' => $visible && $incomplete !== [],
            'items' => $incomplete,
        ];
    }

    /**
     * @param  array<string, int>  $setupCounts
     * @return array<string, mixed>
     */
    private function basicsSection(Staff $staff, int $siteId, array $setupCounts, bool $visible): array
    {
        $items = [
            $this->item('site-profile', '场馆信息', null, '/pages/settings/site/index', 'site.profile.form', 'site.profile.read', $staff, $siteId, true, false),
            $this->item('room-catalog', '教室管理', null, '/pages/settings/rooms/index', 'site.room.list', 'site.rooms.read', $staff, $siteId, true, false),
            $this->item('card-products', '会员卡', null, '/pages/settings/card-products/index', 'card-product.catalog', 'card-product.catalog.read', $staff, $siteId, true, false),
            $this->item('staff-directory', '教练/员工', null, '/pages/settings/staff/index', 'staff.directory.list', 'staff.directory.read', $staff, $siteId, true, false),
            $this->item('course-catalog', '课程库', null, '/pages/settings/courses/index', 'course-catalog.read', 'course-catalog.read', $staff, $siteId, true, false),
            $this->item('card-course-links', '卡・课关联', '设置每张卡可预约的课程及课时费', '/pages/settings/card-products/course-matrix', 'card-product.course-scope', 'card-product.editor.write', $staff, $siteId, true, false),
            $this->item('schedule-sessions', '排课/课程', null, '/pages/course/timetable/index', 'schedule.session.read', 'schedule.session.read', $staff, $siteId, true, false),
        ];

        $enabledItems = array_values(array_filter($items, fn (array $item) => $item['enabled']));

        return [
            'key' => 'basics',
            'label' => '基础设置',
            'legacyFlag' => 'shop_basics',
            'visible' => $visible && $enabledItems !== [],
            'items' => $enabledItems,
        ];
    }

    private function defaultSection(Staff $staff, int $siteId, bool $visible): array
    {
        $items = [
            $this->item('booking-policy', '预约设置', null, '/pages/settings/booking-policy/index', 'booking.policy.read', 'booking.policy.read', $staff, $siteId, true, false),
            $this->item('notification-channels', '提醒设置', null, '/pages/settings/defaults/notification-channels/index', 'notification.channel.config', 'notification.channel.config.read', $staff, $siteId, true, false),
            $this->item('card-reminder-config', '会员卡提醒阈值', null, '/pages/settings/defaults/card-reminder-config/index', 'member-card.reminder.config', 'member-card.reminder.config', $staff, $siteId, true, false),
            $this->item('crm-field-config', '会员资料', null, '/pages/settings/crm/field-config/index', 'tenant.crm.field-config', 'tenant.crm.field-config.write', $staff, $siteId, true, false),
            $this->item('membership-agreement', '会员协议', null, '/pages/settings/legal/membership-agreement/index', 'tenant.legal.membership-agreement.read', 'tenant.legal.membership-agreement.read', $staff, $siteId, true, false),
            $this->item('payment-marketing', '收款帐户', null, '/pages/settings/defaults/payment-marketing/index', 'tenant.payment.marketing', 'tenant.settings.read', $staff, $siteId, true, false),
            $this->item('venue-qr', '员工小程序码', null, '/pages/settings/sharing/staff-miniapp-code/index', 'tenant.venue.qr', 'tenant.settings.read', $staff, $siteId, true, false),
        ];

        $enabledItems = array_values(array_filter($items, fn (array $item) => $item['enabled']));

        return [
            'key' => 'defaults',
            'label' => '默认配置',
            'legacyFlag' => 'shop_default',
            'visible' => $visible && $enabledItems !== [],
            'items' => $enabledItems,
        ];
    }

    private function memberExperienceSection(Staff $staff, int $siteId, bool $visible): array
    {
        $items = [
            $this->item('member-booking-help', '会员如何约课', null, '/pages/settings/member/onboarding-help/index', 'tenant.member.onboarding-help', 'tenant.member-experience.read', $staff, $siteId, true, false),
            $this->item('member-home-carousel', '场馆展示图', null, '/pages/settings/member/carousel/index', 'tenant.member.home-carousel', 'tenant.member-experience.read', $staff, $siteId, true, false),
            $this->item('member-warm-hint', '温馨提示', null, '/pages/settings/member/warm-hint/index', 'tenant.member.warm-hint', 'tenant.member-experience.read', $staff, $siteId, true, false),
            $this->item('member-miniapp-layout', '显示/隐藏', null, '/pages/settings/member/miniapp-layout/index', 'tenant.member.miniapp-layout', 'tenant.member-experience.read', $staff, $siteId, true, false),
        ];

        $enabledItems = array_values(array_filter($items, fn (array $item) => $item['enabled']));

        return [
            'key' => 'member-experience',
            'label' => '会员端配置',
            'legacyFlag' => 'shop_member_config',
            'visible' => $visible && $enabledItems !== [],
            'items' => $enabledItems,
        ];
    }

    private function operationsSection(Staff $staff, int $siteId, bool $visible): array
    {
        $items = [
            $this->item('site-closures', '节假日闭店', null, '/pages/settings/operations/closure-calendar/index', 'tenant.site.closure-calendar', 'tenant.site.closure-calendar.read', $staff, $siteId, true, false),
            $this->item('coach-vacation', '教练请假', null, '/pages/settings/operations/staff-vacations/index', 'tenant.staff.vacation.list', 'tenant.staff.vacation.read', $staff, $siteId, true, false),
            $this->item('announcements', '发布公告', null, '/pages/settings/operations/notices/index', 'notice.announcement.list', 'notice.announcement.read', $staff, $siteId, true, false),
            $this->item('data-export', '数据导出', '导出会员资料与售卡记录', '/pages/report/exports/index', 'export.member.export', 'export.member.create', $staff, $siteId, true, false),
        ];

        $enabledItems = array_values(array_filter($items, fn (array $item) => $item['enabled']));

        return [
            'key' => 'operations',
            'label' => '店长工具',
            'legacyFlag' => 'shop_manager_tool',
            'visible' => $visible && $enabledItems !== [],
            'items' => $enabledItems,
        ];
    }

    private function chainSection(Staff $staff, int $siteId, bool $visible): array
    {
        $items = [
            $this->item('chain-instructions', '如何设置', null, '/pages/settings/chain/instructions/index', 'tenant.chain.instructions', 'tenant.settings.chain.read', $staff, $siteId, true, false),
            $this->item('chain-stores', '分店管理', null, '/pages/settings/chain/stores/index', 'site.context.list', 'organization.site.read', $staff, $siteId, true, false),
            $this->item('chain-shared-cards', '连锁通用卡', null, '/pages/settings/chain/cross-site-cards/index', 'tenant.chain.shared-cards', 'card-product.catalog.read', $staff, $siteId, true, false),
            $this->item('chain-store-courses', '适用店与课', null, '/pages/settings/chain/store-courses/index', 'tenant.chain.store-courses', 'card-product.editor.write', $staff, $siteId, true, false),
            $this->item('chain-card-stats', '售卡统计', null, '/pages/report/card-sales/index', 'report.chain.card-sales', 'report.read', $staff, $siteId, true, false),
            $this->item('chain-course-stats', '课时费统计', null, '/pages/report/courses/index', 'report.chain.course-fees', 'report.course.read', $staff, $siteId, true, false),
            $this->item('chain-staff', '总店员工', null, '/pages/settings/chain/staff/index', 'tenant.chain.staff', 'staff.directory.read', $staff, $siteId, true, false),
        ];

        $enabledItems = array_values(array_filter($items, fn (array $item) => $item['enabled']));

        return [
            'key' => 'chain',
            'label' => '连锁配置',
            'legacyFlag' => 'multiple_shop_config',
            'visible' => $visible && $enabledItems !== [],
            'items' => $enabledItems,
        ];
    }

    private function supportSection(Staff $staff, int $siteId, bool $visible): array
    {
        $items = [
            $this->item('customer-service', '联系客服', null, '/pages/settings/support/customer-service/index', 'tenant.support.contact', 'tenant.settings.support.read', $staff, $siteId, true, false),
            $this->item('platform-subscription-orders', '平台订阅订单', null, '/pages/settings/platform/subscription-orders/index', 'platform.subscription.orders', 'platform.subscription.read', $staff, $siteId, true, false),
            $this->item('video-help', '视频帮助', null, '/pages/settings/support/video-help/index', 'tenant.support.video-help', 'tenant.settings.support.read', $staff, $siteId, true, false),
        ];

        $enabledItems = array_values(array_filter($items, fn (array $item) => $item['enabled']));

        return [
            'key' => 'support',
            'label' => '服务中心',
            'legacyFlag' => 'shop_service_center',
            'visible' => $visible && $enabledItems !== [],
            'items' => $enabledItems,
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     description: ?string,
     *     route: ?string,
     *     capability: string,
     *     requiredPermission: string,
     *     enabled: bool,
     *     implemented: bool,
     *     setupIncomplete: bool
     * }
     */
    private function item(
        string $key,
        string $label,
        ?string $description,
        ?string $route,
        string $capability,
        string $requiredPermission,
        Staff $staff,
        int $siteId,
        bool $implemented,
        bool $setupIncomplete,
    ): array {
        $enabled = $staff->hasPermission($requiredPermission, $siteId);

        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'route' => $this->miniappRoute($route),
            'capability' => $capability,
            'requiredPermission' => $requiredPermission,
            'enabled' => $enabled,
            'implemented' => $implemented,
            'setupIncomplete' => $setupIncomplete,
        ];
    }

    private function miniappRoute(?string $route): ?string
    {
        if ($route === null) {
            return null;
        }

        foreach (['/pages/settings/', '/pages/course/', '/pages/report/'] as $legacyPrefix) {
            if (str_starts_with($route, $legacyPrefix)) {
                return str_replace('/pages/', '/subpackages/', $route);
            }
        }

        return $route;
    }
}
