<?php

use Estouai\MapPoint\Http\Controllers\GeocodeController;
use Illuminate\Support\Facades\Route;

// Every keystroke an editor types is a billable Google request, so the
// throttle is a cost control as much as an abuse control.
Route::middleware('throttle:60,1')->prefix('map-point')->name('map-point.')->group(function () {
    Route::get('search', [GeocodeController::class, 'search'])->name('search');
    Route::get('details', [GeocodeController::class, 'details'])->name('details');
});
