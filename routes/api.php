<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\LinkController;
use App\Http\Controllers\Api\LinkGroupController;
use Illuminate\Support\Facades\Route;

// Key verification: any valid key, no specific permission required
Route::middleware(['api.key'])->group(function () {
    Route::get('/me', [AccountController::class, 'me']);
});

// Links API routes with permission-based middleware
Route::middleware(['api.key:links:read'])->group(function () {
    Route::get('/links', [LinkController::class, 'index']);
    Route::get('/links/{id}', [LinkController::class, 'show']);
});

Route::middleware(['api.key:links:create'])->group(function () {
    Route::post('/links', [LinkController::class, 'store']);
    Route::post('/simple', [LinkController::class, 'simpleStore']);
});

Route::middleware(['api.key:links:update'])->group(function () {
    Route::put('/links/{id}', [LinkController::class, 'update']);
    Route::patch('/links/{id}', [LinkController::class, 'update']);
});

Route::middleware(['api.key:links:delete'])->group(function () {
    Route::delete('/links/{id}', [LinkController::class, 'destroy']);
});

Route::middleware(['api.key:stats:read'])->group(function () {
    Route::get('/links/{id}/stats', [LinkController::class, 'stats']);
});

// Link Groups API routes
Route::middleware(['api.key:groups:read'])->group(function () {
    Route::get('/groups', [LinkGroupController::class, 'index']);
    Route::get('/groups/{id}', [LinkGroupController::class, 'show']);
});

Route::middleware(['api.key:groups:create'])->group(function () {
    Route::post('/groups', [LinkGroupController::class, 'store']);
});

Route::middleware(['api.key:groups:update'])->group(function () {
    Route::put('/groups/{id}', [LinkGroupController::class, 'update']);
    Route::patch('/groups/{id}', [LinkGroupController::class, 'update']);
});

Route::middleware(['api.key:groups:delete'])->group(function () {
    Route::delete('/groups/{id}', [LinkGroupController::class, 'destroy']);
});
