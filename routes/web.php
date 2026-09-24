<?php

use App\Http\Controllers\ServeCover;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

/*
 * The Covers are served by the application and not by the disk (BIB-32). The
 * route lives here, and not where the framework puts the one for `/storage`,
 * so that caching the routes does not take it away; and its address carries no
 * file extension, so the day static handling gets turned on in nginx it does
 * not get intercepted before reaching PHP.
 */
Route::get('covers/{book}/{size}', ServeCover::class)->name('cover');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('shelf', 'pages::shelf')->name('shelf');

    Route::livewire('scan', 'pages::scan')->name('scan');

    Route::livewire('title-lookup', 'pages::title-lookup')->name('title-lookup');

    Route::livewire('manual-entry', 'pages::manual-entry')->name('manual-entry');

    Route::livewire('books/{book}', 'pages::book')->name('book');
});

require __DIR__.'/settings.php';
