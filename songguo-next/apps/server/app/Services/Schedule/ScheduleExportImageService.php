<?php

namespace App\Services\Schedule;

use App\Models\Site;
use App\Models\Staff;

class ScheduleExportImageService
{
    /**
     * @return array{imageUrl: string, width: int, height: int, placeholder: bool}
     */
    public function export(Staff $staff, Site $site, array $payload): array
    {
        return [
            'imageUrl' => 'https://placeholder.songguo.local/schedule-export/'.$site->id.'.png',
            'width' => 1080,
            'height' => 1920,
            'placeholder' => true,
            'from' => $payload['from'] ?? null,
            'to' => $payload['to'] ?? null,
        ];
    }
}
