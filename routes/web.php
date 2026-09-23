<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('captura', 'pages::captura')->name('captura');

    Route::livewire('captura-manual', 'pages::captura-manual')->name('captura-manual');
});

require __DIR__.'/settings.php';
