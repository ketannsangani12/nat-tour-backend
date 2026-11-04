<?php

use App\Http\Controllers\API\V1\Image\ImageController;
use Illuminate\Support\Facades\Route;

// No auth required - images are already public on S3, this just bypasses CORS
// Path is restricted to images/tour-operator/ in the controller
Route::get('/image/proxy', [ImageController::class, 'proxy'])->name('image.proxy');
Route::get('/image/base64', [ImageController::class, 'toBase64'])->name('image.base64');
