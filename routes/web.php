<?php

use App\Http\Controllers\StreamController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::livewire('/login', 'pages::auth.login')->name('login');
    Route::livewire('/register', 'pages::auth.register')->name('register');
});

Route::middleware('auth')->group(function (): void {
    Route::livewire('/chat/{conversationId?}', 'pages::chat')->name('chat');
    Route::post('/chat/stream', StreamController::class)->name('chat.stream');
});
