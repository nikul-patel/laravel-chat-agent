<?php

use App\Http\Controllers\Api\ChatController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('chat-agent')
    ->middleware(['throttle:chat-agent'])
    ->group(function (): void {
        Route::post('/message', ChatController::class)->name('chat-agent.message');
        Route::get('/history', [ChatController::class, 'history'])->name('chat-agent.history');
    });
