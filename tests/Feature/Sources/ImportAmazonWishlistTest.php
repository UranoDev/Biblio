<?php

use App\Enums\Source;
use App\Jobs\DownloadCover;
use App\Jobs\ImportAmazonWishlist;
use App\Models\Book;
use App\Models\WishlistSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    Queue::fake();
});

/** Minimal wishlist HTML with one Amazon book item that carries a cover image. */
function itemHtml(
    string $coliid = 'ABC123',
    string $asin = '6079681056',
    string $title = 'El libro de César Millán',
    string $imgSrc = 'https://m.media-amazon.com/images/I/71abc._SX300_.jpg',
): string {
    return <<<HTML
    <html><body>
    <div id="item_{$coliid}">
      <img id="itemImage_{$coliid}" src="{$imgSrc}" alt="cover">
      <a id="itemName_{$coliid}" href="/dp/{$asin}/ref=wl_it" title="{$title}">{$title}</a>
      <span class="a-size-small">by César Millán</span>
    </div>
    </body></html>
    HTML;
}

it('actualiza la portada de un Libro que ya existía y no tenía portada', function () {
    $book = Book::factory()->create([
        'isbn'       => '9786079681050',
        'source'     => Source::Amazon,
        'cover_path' => null,
    ]);

    WishlistSetting::configure('https://www.amazon.com.mx/hz/wishlist/ls/EXAMPLE');

    Http::fake(['https://www.amazon.com.mx/*' => Http::response(itemHtml())]);

    // dispatchSync() routes ShouldQueue jobs through the fake queue without calling
    // handle(), so Queue::fake() would never see the inner DownloadCover dispatch.
    app()->call([new ImportAmazonWishlist(), 'handle']);

    expect(Book::where('isbn', '9786079681050')->count())->toBe(1);

    Queue::assertPushed(DownloadCover::class);
});

it('no toca la portada de un Libro que ya existía y ya la tenía', function () {
    Book::factory()->create([
        'isbn'       => '9786079681050',
        'source'     => Source::Amazon,
        'cover_path' => 'covers/1/existing.jpg',
    ]);

    WishlistSetting::configure('https://www.amazon.com.mx/hz/wishlist/ls/EXAMPLE');

    Http::fake(['https://www.amazon.com.mx/*' => Http::response(itemHtml())]);

    app()->call([new ImportAmazonWishlist(), 'handle']);

    Queue::assertNotPushed(DownloadCover::class);
});

it('no encola descarga cuando el item de la lista no tiene imagen', function () {
    Book::factory()->create([
        'isbn'       => '9786079681050',
        'source'     => Source::Amazon,
        'cover_path' => null,
    ]);

    WishlistSetting::configure('https://www.amazon.com.mx/hz/wishlist/ls/EXAMPLE');

    Http::fake(['https://www.amazon.com.mx/*' => Http::response(<<<'HTML'
    <html><body>
    <div id="item_XYZ">
      <a id="itemName_XYZ" href="/dp/6079681056/ref=wl" title="Sin imagen">Sin imagen</a>
    </div>
    </body></html>
    HTML)]);

    app()->call([new ImportAmazonWishlist(), 'handle']);

    Queue::assertNotPushed(DownloadCover::class);
});
