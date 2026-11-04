<?php

use App\Http\Controllers\API\V1\TourPackage\TourPackageController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::get('/packages/{id}', [TourPackageController::class, 'packages'])->name('packages');
});
