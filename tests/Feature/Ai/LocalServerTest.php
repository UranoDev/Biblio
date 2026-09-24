<?php

use App\Ai\LocalServer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

it('dice que sí cuando algo contesta en la URL', function () {
    Http::fake(['http://localhost:11434' => Http::response('Ollama is running')]);

    expect(app(LocalServer::class)->responds('http://localhost:11434'))->toBeTrue();

    Http::assertSent(fn ($request): bool => $request->url() === 'http://localhost:11434');
});

it('dice que no cuando la conexión se rechaza, y no revienta', function () {
    // Un servidor apagado no es un error que reportar: se puede encender
    // después, y la pantalla lo sigue ofreciendo igual.
    Http::fake(fn () => throw new ConnectionException('Connection refused.'));

    expect(app(LocalServer::class)->responds('http://localhost:11434'))->toBeFalse();
});

it('dice que no cuando el servidor contesta con un error', function () {
    Http::fake(['http://localhost:11434' => Http::response('', 500)]);

    expect(app(LocalServer::class)->responds('http://localhost:11434'))->toBeFalse();
});

it('no pregunta nada cuando no hay URL configurada', function () {
    Http::fake();

    $server = app(LocalServer::class);

    expect($server->responds(null))->toBeFalse()
        ->and($server->responds('   '))->toBeFalse();

    Http::assertNothingSent();
});

it('pregunta una sola vez por la misma URL', function () {
    // La pantalla lo consulta desde más de un lugar mientras se dibuja, y
    // dibujar una pantalla no es motivo para tocar dos veces la misma puerta.
    Http::fake(['http://localhost:11434' => Http::response('Ollama is running')]);

    $server = app(LocalServer::class);

    $server->responds('http://localhost:11434');
    $server->responds('http://localhost:11434');

    Http::assertSentCount(1);
});
