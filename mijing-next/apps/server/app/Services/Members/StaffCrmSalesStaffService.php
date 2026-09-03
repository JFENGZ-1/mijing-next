<?php

namespace App\Services\Members;

use App\Models\Site;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;

class StaffCrmSalesStaffService
{
    /**
     * Active staff at the site eligible as owner / 会籍顾问 on member forms.
     *
     * @return list<array{id: int, name: string, employeeNo: string}>
     */
    public function list(Staff $actor, Site $site): array
    {
        return Staff::query()
            ->where('staff.tenant_id', $actor->tenant_id)
            ->where('staff.status', 'active')
            ->whereHas('sites', fn (Builder $sites) => $sites
                ->whereKey($site->id)
                ->where('site_staff.tenant_id', $actor->tenant_id))
            ->orderBy('staff.name')
            ->get(['staff.id', 'staff.name', 'staff.employee_no'])
            ->map(fn (Staff $staff) => [
                'id' => $staff->id,
                'name' => $staff->name,
                'employeeNo' => $staff->employee_no,
            ])
            ->all();
    }
}
