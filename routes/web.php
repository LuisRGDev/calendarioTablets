<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\EventController;
use App\Http\Middleware\CheckAdminPin;
use Illuminate\Support\Facades\Route;

// Redirect root to admin
Route::get('/', fn() => redirect('/admin'));

// Admin auth routes (public)
Route::get('/admin/login',   [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login',  [AdminAuthController::class, 'login'])->name('admin.login.post');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

// Admin dashboard (protected)
Route::get('/admin', [AdminController::class, 'index'])
    ->middleware(CheckAdminPin::class)
    ->name('admin.dashboard');

// Room tablet view (public — tablets in the entrance don't need auth)
Route::get('/room/{room:slug}', [RoomController::class, 'show'])->name('room.show');

// Events API (protected by admin middleware for writes, open for reads)
Route::prefix('api')->group(function () {
    Route::get('/events',            [EventController::class, 'index']);
    Route::post('/events',           [EventController::class, 'store'])->middleware(CheckAdminPin::class);
    Route::put('/events/{event}',    [EventController::class, 'update'])->middleware(CheckAdminPin::class);
    Route::delete('/events/{event}', [EventController::class, 'destroy'])->middleware(CheckAdminPin::class);
});
