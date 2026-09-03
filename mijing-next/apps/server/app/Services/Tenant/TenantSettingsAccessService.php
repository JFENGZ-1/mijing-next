<?php

namespace App\Services\Tenant;

use App\Models\Site;
use App\Models\Staff;

class TenantSettingsAccessService
{
    public function assertReadPermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('tenant.settings.read', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function assertFieldConfigWritePermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('tenant.crm.field-config.write', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function assertMemberExperienceReadPermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('tenant.member-experience.read', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function assertMemberExperienceWritePermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('tenant.member-experience.write', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function assertLegalReadPermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('tenant.legal.membership-agreement.read', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function assertLegalWritePermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('tenant.legal.membership-agreement.write', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function assertOperationsReadPermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('tenant.settings.operations.read', $siteId)
            || $staff->hasPermission('tenant.site.closure-calendar.read', $siteId)
            || $staff->hasPermission('tenant.staff.vacation.read', $siteId)
            || $staff->hasPermission('notice.announcement.read', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function assertClosureReadPermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('tenant.site.closure-calendar.read', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function assertClosureWritePermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('tenant.site.closure-calendar.write', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function assertVacationReadPermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('tenant.staff.vacation.read', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function assertVacationWritePermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('tenant.staff.vacation.write', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function assertNotificationChannelReadPermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('notification.channel.config.read', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function assertNotificationChannelWritePermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('notification.channel.config.write', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function assertAnnouncementReadPermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('notice.announcement.read', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function assertAnnouncementWritePermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('notice.announcement.write', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function assertSupportReadPermission(Staff $staff, int $siteId): void
    {
        abort_unless(
            $staff->hasPermission('tenant.settings.support.read', $siteId),
            403,
            'PERMISSION_DENIED',
        );
    }

    public function site(Staff $staff, int $siteId): Site
    {
        return Site::query()
            ->whereKey($siteId)
            ->where('tenant_id', $staff->tenant_id)
            ->where('status', 'active')
            ->whereHas('staff', fn ($query) => $query
                ->whereKey($staff->id)
                ->where('site_staff.tenant_id', $staff->tenant_id))
            ->firstOrFail();
    }
}
