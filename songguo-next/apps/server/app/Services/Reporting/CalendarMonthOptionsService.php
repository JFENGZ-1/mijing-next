<?php

namespace App\Services\Reporting;

use App\Models\Site;
use App\Models\Staff;
use Carbon\Carbon;

class CalendarMonthOptionsService
{
    /**
     * @return array{items: list<array{year: int, month: int, label: string}>}
     */
    public function monthOptions(Staff $staff, Site $site, int $monthsBack = 24): array
    {
        $anchor = Carbon::now()->startOfMonth();
        $items = [];

        for ($i = 0; $i < $monthsBack; $i++) {
            $month = $anchor->copy()->subMonths($i);
            $items[] = [
                'year' => $month->year,
                'month' => $month->month,
                'label' => $month->format('Y年n月'),
            ];
        }

        return [
            'siteId' => $site->id,
            'items' => $items,
        ];
    }
}
