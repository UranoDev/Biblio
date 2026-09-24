<?php

use App\Http\Controllers\ServeCover;
use App\Http\Controllers\ShowShowcase;
use App\Http\Controllers\ShowShowcaseBook;
use App\Http\Controllers\ShowShowcaseCategory;
use App\Http\Controllers\ShowSitemap;
use Illuminate\Support\Facades\Route;

/*
 * The Showcase is the front door of the site (BIB-18). Opening the address
 * without a session shows the collection, not a welcome and not the login.
 *
 * The public page of a Book is `book/{id}` and the back office one is
 * `books/{id}`: they are one letter apart, so `robots.txt` says out loud which
 * of the two it is letting through.
 */
Route::get('/', ShowShowcase::class)->name('showcase');

Route::get('book/{book}', ShowShowcaseBook::class)
    ->whereNumber('book')
    ->name('showcase.book');

/*
 * Walking the Showcase by Category (BIB-19). The address carries the slug and
 * not the id, because it gets shared: `/category/poesia` says where it leads.
 *
 * Singular, like `book/{id}`, and the back office list is `categories`: the two
 * are three letters apart, so `robots.txt` declares each one explicitly.
 */
Route::get('category/{category}', ShowShowcaseCategory::class)
    ->name('showcase.category');

/*
 * The Covers are served by the application and not by the disk (BIB-32). The
 * route lives here, and not where the framework puts the one for `/storage`,
 * so that caching the routes does not take it away; and its address carries no
 * file extension, so the day static handling gets turned on in nginx it does
 * not get intercepted before reaching PHP.
 */
Route::get('covers/{book}/{size}', ServeCover::class)->name('cover');

/*
 * The sitemap (BIB-37). It is generated and not a file in `public/`, so that
 * hiding a Book takes it out of the index without anybody remembering to
 * rebuild anything.
 */
Route::get('sitemap.xml', ShowSitemap::class)->name('sitemap');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('shelf', 'pages::shelf')->name('shelf');

    Route::livewire('quote-search', 'pages::quote-search')->name('quote-search');

    Route::livewire('categories', 'pages::categories')->name('categories');

    Route::livewire('scan', 'pages::scan')->name('scan');

    Route::livewire('title-lookup', 'pages::title-lookup')->name('title-lookup');

    Route::livewire('manual-entry', 'pages::manual-entry')->name('manual-entry');

    Route::livewire('books/{book}', 'pages::book')->name('book');
});

require __DIR__.'/settings.php';
