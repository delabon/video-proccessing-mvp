<?php

use App\Http\Controllers\Api\V1\VideoUploaderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/videos/upload', VideoUploaderController::class)
        ->middleware('auth:sanctum');
});
