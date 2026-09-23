<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('captura', 'pages::captura')->name('captura');

    Route::livewire('alta-por-titulo', 'pages::alta-por-titulo')->name('alta-por-titulo');

    Route::livewire('captura-manual', 'pages::captura-manual')->name('captura-manual');
});

require __DIR__.'/settings.php';
