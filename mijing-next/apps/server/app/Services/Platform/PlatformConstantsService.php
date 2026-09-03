<?php

namespace App\Services\Platform;

class PlatformConstantsService
{
    public function regions(): array
    {
        return config('china_regions');
    }
}
