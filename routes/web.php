<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Chat\ChannelController;
use App\Http\Controllers\Chat\DirectMessageController;
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
    Route::get('/chat', [ChannelController::class, 'home'])->name('chat.index');
    Route::post('/channels', [ChannelController::class, 'store'])->name('channels.store');
    Route::get('/channels/{channel}', [ChannelController::class, 'show'])->name('channels.show');
    Route::post('/channels/{channel}/join', [ChannelController::class, 'join'])->name('channels.join');
    Route::post('/channels/{channel}/leave', [ChannelController::class, 'leave'])->name('channels.leave');
    Route::post('/channels/{channel}/members', [ChannelController::class, 'invite'])->name('channels.invite');
    Route::post('/channels/{channel}/close', [ChannelController::class, 'close'])->name('channels.close');

    // ─── Direct messages ──────────────────────────────────────────────────────
    Route::post('/direct-messages', [DirectMessageController::class, 'store'])->name('direct-messages.store');
    Route::get('/users/search', [DirectMessageController::class, 'searchUsers'])->name('users.search');

    // ─── Messages ─────────────────────────────────────────────────────────────
    Route::get('/channels/{channel}/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/channels/{channel}/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::post('/channels/{channel}/read', [MessageController::class, 'markRead'])->name('messages.read');
});

// ─── Root redirect ───────────────────────────────────────────────────────────
Route::get('/', fn () => redirect()->route('login'));
