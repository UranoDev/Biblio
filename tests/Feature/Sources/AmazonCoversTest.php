<?php

use App\Domain\Isbn;
use App\Sources\AmazonCovers;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function amazonCovers(): AmazonCovers
{
    return new AmazonCovers(url: 'https://images-na.ssl-images-amazon.com', timeout: 10);
}

/** «La estrella de Ratner», Seix Barral México: Google Books knows it without a Cover. */
it('da la dirección de la portada cuando Amazon tiene la imagen', function () {
    Http::fake(['images-na.ssl-images-amazon.com/*' => Http::response(recordedCover('amazon-6070725360.jpg'))]);

    expect(amazonCovers()->coverUrl(Isbn::from('9786070725364')))
        ->toBe('https://images-na.ssl-images-amazon.com/images/P/6070725360.01.LZZZZZZZ.jpg');
});

it('pide la imagen por el ISBN de diez dígitos', function () {
    Http::fake(['images-na.ssl-images-amazon.com/*' => Http::response(recordedCover('amazon-6070725360.jpg'))]);

    amazonCovers()->coverUrl(Isbn::from('9786070725364'));

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/images/P/6070725360.01.LZZZZZZZ.jpg'));
});

it('no da dirección cuando Amazon contesta el pixel vacío', function () {
    Http::fake(['images-na.ssl-images-amazon.com/*' => Http::response(recordedCover('amazon-empty-pixel.gif'))]);

    expect(amazonCovers()->coverUrl(Isbn::from('9781621364733')))->toBeNull();
});

it('no pregunta por un ISBN que empieza con 979, que no tiene forma de diez dígitos', function () {
    Http::fake();

    expect(amazonCovers()->coverUrl(Isbn::from('9798902680222')))->toBeNull();

    Http::assertNothingSent();
});

it('no da dirección cuando Amazon no contesta', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    expect(amazonCovers()->coverUrl(Isbn::from('9786070725364')))->toBeNull();
});

it('no da dirección cuando Amazon contesta un error', function () {
    Http::fake(['images-na.ssl-images-amazon.com/*' => Http::response('', 503)]);

    expect(amazonCovers()->coverUrl(Isbn::from('9786070725364')))->toBeNull();
});
