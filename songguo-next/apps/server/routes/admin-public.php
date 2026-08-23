<?php

use App\Http\Controllers\Api\V1\PublicMediaAssetController;
use Illuminate\Support\Facades\Route;

Route::get('/media/{uuid}', PublicMediaAssetController::class)
    ->whereUuid('uuid')
    ->name('platform-media.show');
