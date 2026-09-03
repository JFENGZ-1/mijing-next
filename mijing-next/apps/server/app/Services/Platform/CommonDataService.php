<?php

namespace App\Services\Platform;

class CommonDataService
{
    /**
     * @return array<string, mixed>
     */
    public function commonData(): array
    {
        return [
            'uploadUrl' => config('app.url').'/api/v1/uploads',
            'defaultAvatarUrl' => config('app.url').'/assets/default-avatar.png',
            'defaultSiteImageUrl' => config('app.url').'/assets/default-site.png',
            'dictVal' => [
                'cardTypes' => ['stored_value', 'count', 'period'],
                'courseTypes' => ['group', 'private'],
            ],
        ];
    }
}
