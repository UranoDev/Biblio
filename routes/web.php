<?php

use App\Http\Controllers\DownloadQuoteImage;
use App\Http\Controllers\ServeBookImage;
use App\Http\Controllers\ServeCover;
use App\Http\Controllers\ServeQuoteImage;
use App\Http\Controllers\ShowPrivacyNotice;
use App\Http\Controllers\ShowShowcase;
use App\Http\Controllers\ShowShowcaseBook;
use App\Http\Controllers\ShowShowcaseCategory;
use App\Http\Controllers\ShowShowcaseQuote;
use App\Http\Controllers\ShowSitemap;
use App\Http\Controllers\ShowTermsOfUse;
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
 * The card a network draws when the link of a Book is pasted (BIB-50).
 *
 * The address carries a signature of what the card was drawn from, for the same
 * reason the images of a Quote do: the networks cache `og:image` by URL and with
 * a will, and without it changing the card keeps showing the old one for days.
 *
 * It hangs off `book/{id}` and not off an address of its own, so `robots.txt`
 * lets it through with the sheet it belongs to and there is nothing new to
 * declare by hand.
 */
Route::get('book/{book}/card/{fingerprint}', ServeBookImage::class)
    ->whereNumber('book')
    ->name('book.card');

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
 * The page of one Quote (BIB-31). It is the main tool for sharing, so its
 * address is the short one: `quote/123`, and the number never changes.
 *
 * `quote-search` is the back office screen and it is three segments away from
 * this one, so `robots.txt` declares each of the two explicitly.
 */
Route::get('quote/{quote}', ShowShowcaseQuote::class)
    ->whereNumber('quote')
    ->name('showcase.quote');

/*
 * The two shareable images of a Quote (ADR-009).
 *
 * The address carries a signature of what the image was drawn from, because
 * the networks cache `og:image` by URL and with a will: without it, changing
 * the image keeps showing the old one for days.
 *
 * They are served by the application and not as loose files on the disk for the
 * same reason the Covers are: a static file survives its Book being Hidden, and
 * that is a mistake that goes outwards and in silence (ADR-003).
 */
Route::get('quote/{quote}/{format}/{fingerprint}', ServeQuoteImage::class)
    ->whereNumber('quote')
    ->name('quote.image');

/*
 * The vertical one, as a file with a name a person reads. It exists because
 * Instagram reads no tags: there the image is uploaded by hand.
 */
Route::get('quote/{quote}/download', DownloadQuoteImage::class)
    ->whereNumber('quote')
    ->name('quote.download');

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

/*
 * The privacy notice and terms of use (BIB-47). Two static pages, one route
 * each, reached from the footer of every Showcase page.
 */
Route::get('privacidad', ShowPrivacyNotice::class)->name('showcase.privacy');

Route::get('terminos', ShowTermsOfUse::class)->name('showcase.terms');

Route::middleware(['auth', 'verified'])->group(function () {
    /*
     * The first screen of the back office (BIB-40). It is no longer where the
     * login lands -- that is the Shelf -- and it is no longer the three
     * placeholders of the skeleton: it says where the pending work is.
     */
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::livewire('shelf', 'pages::shelf')->name('shelf');

    Route::livewire('quote-search', 'pages::quote-search')->name('quote-search');

    Route::livewire('categories', 'pages::categories')->name('categories');

    Route::livewire('scan', 'pages::scan')->name('scan');

    Route::livewire('title-lookup', 'pages::title-lookup')->name('title-lookup');

    Route::livewire('manual-entry', 'pages::manual-entry')->name('manual-entry');

    Route::livewire('books/{book}', 'pages::book')->name('book');

    Route::livewire('style-guide', 'pages::style-guide')->name('style-guide');
});

require __DIR__.'/settings.php';
