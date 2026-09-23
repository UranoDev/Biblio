<?php

use App\Domain\InvalidIsbn;
use App\Domain\Isbn;

it('acepta un ISBN-13 bien formado', function () {
    expect(Isbn::from('9788437604176')->value())->toBe('9788437604176');
});

it('acepta un ISBN-10 y lo normaliza a trece dígitos', function () {
    expect(Isbn::from('8437604176')->value())->toBe('9788437604176');
});

it('llega al mismo resultado con el ISBN-10 y con el ISBN-13 del mismo Book', function () {
    expect(Isbn::from('8437604176')->equals(Isbn::from('9788437604176')))->toBeTrue();
});

it('ignora los guiones y los espacios con que viene impreso', function () {
    expect(Isbn::from(' 978-84-376-0417-6 ')->value())->toBe('9788437604176')
        ->and(Isbn::from('84 376 0417 6')->value())->toBe('9788437604176');
});

it('acepta la X del dígito de control de un ISBN-10, en mayúscula o minúscula', function () {
    expect(Isbn::from('043942089X')->value())->toBe('9780439420891')
        ->and(Isbn::from('043942089x')->value())->toBe('9780439420891');
});

it('rechaza un ISBN-13 con el dígito de control equivocado', function () {
    expect(fn () => Isbn::from('9788437604177'))->toThrow(InvalidIsbn::class);
});

it('rechaza un ISBN-10 con el dígito de control equivocado', function () {
    expect(fn () => Isbn::from('8437604175'))->toThrow(InvalidIsbn::class);
});

it('rechaza lo que no tiene forma de ISBN', function (string $value) {
    expect(fn () => Isbn::from($value))->toThrow(InvalidIsbn::class);
})->with(['', '12345', '97884376041760', 'no soy un isbn', '84376X4176', '978843760417X']);

it('dice si un texto es un ISBN sin lanzar excepción', function () {
    expect(Isbn::isValid('9788437604176'))->toBeTrue()
        ->and(Isbn::isValid('9788437604177'))->toBeFalse();
});

it('devuelve el ISBN-10 equivalente cuando existe', function () {
    expect(Isbn::from('9788437604176')->asTen())->toBe('8437604176')
        ->and(Isbn::from('9780439420891')->asTen())->toBe('043942089X');
});

it('no inventa un ISBN-10 para los que no empiezan con 978', function () {
    expect(Isbn::from('9791234567896')->asTen())->toBeNull();
});

it('se imprime como el ISBN de trece dígitos', function () {
    expect((string) Isbn::from('8437604176'))->toBe('9788437604176');
});
