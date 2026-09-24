<?php

use App\Showcase\Robots;
use Illuminate\Routing\Route as RoutedAddress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/*
 * `robots.txt` is written by hand, so what this file checks is not that it says
 * what somebody typed into it: it walks the routes of the application and asks
 * the file about each one. A screen added behind the login tomorrow does not
 * edit the file, and nothing on the screen would say so — this does (BIB-37).
 */

/** Every address the application answers only to somebody who logged in, or only to somebody who did not. */
function behindALogin(): Collection
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutedAddress $route): bool => in_array('GET', $route->methods(), true))
        ->filter(fn (RoutedAddress $route): bool => collect($route->gatherMiddleware())
            ->contains(fn (mixed $middleware): bool => is_string($middleware)
                && preg_match('/^(auth|guest)(:|$)/', $middleware) === 1))
        // A crawler asks for an address and not for a pattern, so the
        // placeholders get a value: the rules are prefixes, and any value walks
        // the same one.
        ->map(fn (RoutedAddress $route): string => '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri()));
}

it('cierra toda pantalla que la aplicación sirve detrás del login', function () {
    $robots = Robots::served();
    $screens = behindALogin();

    // If this is empty the test is passing over nothing, which is the one way
    // it could stop protecting anything without failing.
    expect($screens)->not->toBeEmpty();

    foreach ($screens as $screen) {
        expect($robots->allows($screen))->toBeFalse("robots.txt deja pasar {$screen}");
    }
});

it('deja pasar el acervo, sus portadas y el sitemap', function (string $address) {
    expect(Robots::served()->allows($address))->toBeTrue();
})->with([
    '/',
    '/book/1',
    '/category/poesia',
    '/covers/1/full',
    '/sitemap.xml',
]);

it('distingue la ficha pública de la del back office, que difieren en una letra', function () {
    $robots = Robots::served();

    expect($robots->allows('/book/1'))->toBeTrue()
        ->and($robots->allows('/books/1'))->toBeFalse()
        ->and($robots->allows('/category/poesia'))->toBeTrue()
        ->and($robots->allows('/categories'))->toBeFalse();
});

it('anuncia el sitemap que el sitio sirve', function () {
    $robots = file_get_contents(public_path('robots.txt'));

    expect($robots)->toContain('Sitemap: ')
        ->and($robots)->toContain('/sitemap.xml');
});

it('deja abierto lo que ninguna regla nombra, y la regla más larga gana', function () {
    $robots = Robots::parse(<<<'ROBOTS'
        User-agent: *
        Disallow: /libros/
        Allow: /libros/publicos/
        Disallow:
        ROBOTS);

    expect($robots->allows('/citas/1'))->toBeTrue()
        ->and($robots->allows('/libros/1'))->toBeFalse()
        ->and($robots->allows('/libros/publicos/1'))->toBeTrue();
});
