<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Chat\ChannelController;
use App\Http\Controllers\Chat\MessageController;
use Illuminate\Support\Facades\Route;

// ─── Guest routes ────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// ─── Auth routes ─────────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // ─── Channels ─────────────────────────────────────────────────────────────
    Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');
    Route::get('/chat', [ChannelController::class, 'index'])->name('chat.index');
    Route::post('/channels', [ChannelController::class, 'store'])->name('channels.store');
    Route::get('/channels/{channel}', [ChannelController::class, 'show'])->name('channels.show');
    Route::post('/channels/{channel}/join', [ChannelController::class, 'join'])->name('channels.join');

    // ─── Messages ─────────────────────────────────────────────────────────────
    Route::get('/channels/{channel}/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/channels/{channel}/messages', [MessageController::class, 'store'])->name('messages.store');
});

// ─── Root redirect ───────────────────────────────────────────────────────────
Route::get('/', fn () => redirect()->route('login'));
