<?php

use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Custom redirect URL takes precedence over admin redirect
    if ($customUrl = config('shortener.homepage.redirect_url')) {
        return redirect($customUrl);
    }

    // Redirect to admin panel if configured
    if (config('shortener.homepage.redirect_to_admin')) {
        return redirect('/admin');
    }

    // Use custom view or default welcome page
    $view = config('shortener.homepage.view', 'welcome');

    return view($view);
});

// QR Code routes (must come before the redirect route)
Route::get('/qr/{link}/download', [QrCodeController::class, 'generate'])
    ->name('qr.download')
    ->middleware('auth');

Route::get('/qr/{link}/display', [QrCodeController::class, 'display'])
    ->name('qr.display')
    ->middleware('auth');

// Report routes
Route::get('/reports/{report}/view', [ReportController::class, 'view'])->name('reports.view');
Route::get('/reports/{report}/data', [ReportController::class, 'data'])->name('reports.data');

Route::middleware('auth')->group(function () {
    Route::get('/reports/{report}/builder', [ReportController::class, 'builder'])->name('reports.builder');
    Route::get('/reports/{report}/preview-data', [ReportController::class, 'previewData'])->name('reports.preview-data');
    Route::post('/reports/{report}/components', [ReportController::class, 'updateComponents'])->name('reports.update-components');
    Route::post('/reports/{report}/reorder-components', [ReportController::class, 'reorderComponents'])->name('reports.reorder-components');
});

// Redirect routes with rate limiting (must be last)
Route::get('/{shortCode}', [RedirectController::class, 'redirect'])
    ->name('redirect')
    ->middleware('throttle:60,1')
    ->where('shortCode', '[a-zA-Z0-9\-_]+');

Route::post('/{shortCode}', [RedirectController::class, 'redirect'])
    ->name('redirect.post')
    ->middleware('throttle:60,1')
    ->where('shortCode', '[a-zA-Z0-9\-_]+');
