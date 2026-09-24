<?php

use App\Console\Commands\RecordSourceResponse;

/**
 * Every file of `tests/Fixtures/sources` is a response a Source really gave,
 * written down by `sources:record`. These tests guard the two things that make
 * that worth anything: that the files are still what a catalog answers, and
 * that none of them carries a credential.
 */
function recordings(): array
{
    $files = glob(dirname(__DIR__, 2).'/Fixtures/sources/*.json');

    return array_map(fn (string $path) => [basename($path)], $files ?: []);
}

it('tiene grabaciones que guardar', function () {
    expect(recordings())->not->toBeEmpty();
});

it('guarda JSON que se puede leer', function (string $file) {
    $body = recorded(pathinfo($file, PATHINFO_FILENAME));

    expect($body)->not->toBe('')
        ->and(json_decode($body, true))->toBeArray();
})->with(recordings());

/**
 * A response can echo the request back —Google puts the URL it answered in
 * `selfLink`— and with it the key. `sources:record` takes it out before
 * writing; this is what notices the day it does not.
 */
it('no guarda nada con forma de credencial', function (string $file) {
    $body = recorded(pathinfo($file, PATHINFO_FILENAME));

    $suspicious = [
        'una llave de Google' => '/AIza[0-9A-Za-z_\-]{35}/',
        'una llave en una URL' => '/["&?]key=(?!'.RecordSourceResponse::REDACTED.')[^"&\s\\\\]{8,}/',
        'un token' => '/(?:[Bb]earer|access_token|client_secret|api[_-]?key)["\':\s=]+[A-Za-z0-9_\-\.]{16,}/',
    ];

    foreach ($suspicious as $what => $pattern) {
        expect(preg_match($pattern, $body))->toBe(0, "«{$file}» trae {$what}.");
    }
})->with(recordings());

it('no guarda la llave que está configurada en el entorno', function (string $file) {
    $key = config('sources.google_books.key');

    if (! is_string($key) || trim($key) === '') {
        $this->markTestSkipped('No hay GOOGLE_BOOKS_API_KEY configurada.');
    }

    expect(recorded(pathinfo($file, PATHINFO_FILENAME)))->not->toContain($key);
})->with(recordings());
