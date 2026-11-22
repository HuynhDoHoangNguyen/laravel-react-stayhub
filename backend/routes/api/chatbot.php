<?php

use App\Http\Controllers\Chat\ChatMessageController;
use App\Http\Controllers\Chat\GuestChatMessageController;
use App\Http\Middleware\RequireChatWebSession;
use Illuminate\Support\Facades\Route;

Route::post('customer/chat/messages', [ChatMessageController::class, 'store'])
    ->middleware(['auth:sanctum', 'account.active', 'role:CUSTOMER', RequireChatWebSession::class])
    ->name('customer.chat.messages.store');

Route::post('public/chat/messages', [GuestChatMessageController::class, 'store'])
    ->name('public.chat.messages.store');
