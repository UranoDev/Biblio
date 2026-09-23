<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('scan', 'pages::scan')->name('scan');

    Route::livewire('title-lookup', 'pages::title-lookup')->name('title-lookup');

    Route::livewire('manual-entry', 'pages::manual-entry')->name('manual-entry');
});

require __DIR__.'/settings.php';
