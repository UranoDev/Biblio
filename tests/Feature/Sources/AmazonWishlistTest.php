<?php

use App\Sources\AmazonWishlist;
use Illuminate\Support\Facades\Http;

/**
 * The Amazon wishlist parser reads an HTML page fetched over HTTP and returns
 * one Candidate per book item it finds. These tests use inline HTML fixtures
 * rather than recorded responses because the wishlist format is HTML, not JSON,
 * and the fixture must represent the structure the parser depends on.
 */

/** Minimal wishlist HTML with one item that has a cover image. */
function wishlistHtml(
    string $coliid = 'ABC123',
    string $asin = '6079681056',
    string $title = 'El libro de César Millán',
    string $author = 'César Millán',
    string $imgSrc = 'https://m.media-amazon.com/images/I/71abc123._SX300_SY300_QL70_.jpg',
): string {
    return <<<HTML
    <html><body>
    <div id="item_{$coliid}">
      <img id="itemImage_{$coliid}" src="{$imgSrc}" alt="cover" class="thumbnail">
      <a id="itemName_{$coliid}" href="/dp/{$asin}/ref=wl_it_dp_o_pC_nS_nC" title="{$title}">{$title}</a>
      <span class="a-size-small a-color-secondary">by {$author}</span>
    </div>
    </body></html>
    HTML;
}

function fakeWishlist(string $html): void
{
    Http::preventStrayRequests();
    Http::fake(['https://www.amazon.com.mx/*' => Http::response($html)]);
}

it('extrae el candidato con portada cuando el item tiene imagen', function () {
    fakeWishlist(wishlistHtml());

    $candidates = (new AmazonWishlist)->candidates('https://www.amazon.com.mx/hz/wishlist/ls/EXAMPLE');

    expect($candidates)->toHaveCount(1);

    $candidate = $candidates[0];

    expect($candidate->title)->toBe('El libro de César Millán')
        ->and($candidate->coverUrl)->toBe('https://m.media-amazon.com/images/I/71abc123._SL500_.jpg');
});

it('normaliza el modificador de tamaño de la URL de portada', function () {
    fakeWishlist(wishlistHtml(imgSrc: 'https://m.media-amazon.com/images/I/81xyz._AC_SX679_.jpg'));

    $candidates = (new AmazonWishlist)->candidates('https://www.amazon.com.mx/hz/wishlist/ls/EXAMPLE');

    expect($candidates[0]->coverUrl)->toBe('https://m.media-amazon.com/images/I/81xyz._SL500_.jpg');
});

it('importa el candidato sin portada cuando el item no tiene imagen', function () {
    $html = <<<'HTML'
    <html><body>
    <div id="item_XYZ">
      <a id="itemName_XYZ" href="/dp/6079681056/ref=wl_it_dp" title="Sin portada">Sin portada</a>
      <span class="a-size-small">by Autor</span>
    </div>
    </body></html>
    HTML;

    fakeWishlist($html);

    $candidates = (new AmazonWishlist)->candidates('https://www.amazon.com.mx/hz/wishlist/ls/EXAMPLE');

    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]->coverUrl)->toBeNull();
});

it('convierte el ASIN numérico al ISBN-13 correcto', function () {
    fakeWishlist(wishlistHtml(asin: '6079681056'));

    $candidates = (new AmazonWishlist)->candidates('https://www.amazon.com.mx/hz/wishlist/ls/EXAMPLE');

    // ISBN-10 6079681056 → ISBN-13 9786079681050
    expect($candidates[0]->isbn->value())->toBe('9786079681050');
});

it('omite items cuyo ASIN no es un ISBN-10 válido', function () {
    $html = <<<'HTML'
    <html><body>
    <a id="itemName_C1" href="/dp/B09ABCDEFG/ref=wl" title="Gadget">Gadget</a>
    <a id="itemName_C2" href="/dp/6079681056/ref=wl" title="Libro válido">Libro válido</a>
    </body></html>
    HTML;

    fakeWishlist($html);

    $candidates = (new AmazonWishlist)->candidates('https://www.amazon.com.mx/hz/wishlist/ls/EXAMPLE');

    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]->title)->toBe('Libro válido');
});

it('lanza SourceUnavailable cuando Amazon no responde', function () {
    Http::fake(['amazon.com.mx/*' => Http::response('', 503)]);

    (new AmazonWishlist)->candidates('https://www.amazon.com.mx/hz/wishlist/ls/EXAMPLE');
})->throws(\App\Sources\SourceUnavailable::class);
