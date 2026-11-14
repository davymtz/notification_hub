<?php

use App\Http\Controllers\V1\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/notifications/dispatch', [NotificationController::class, 'dispatch']);
});
