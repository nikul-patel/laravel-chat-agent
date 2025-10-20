<?php

use Illuminate\Support\Facades\Route;
use Soha\Chat\Http\Controllers\ConversationHistoryController;
use Soha\Chat\Http\Controllers\StreamChatController;

Route::group([
    'as' => config('soha-chat.routes.name', 'soha-chat.'),
    'middleware' => config('soha-chat.middleware', ['web']),
    'prefix' => config('soha-chat.routes.api_prefix', config('soha-chat.prefix', 'soha-chat')),
], function (): void {
    Route::post('/messages', StreamChatController::class)
        ->name('messages');

    Route::get('/messages', ConversationHistoryController::class)
        ->name('history');
});
