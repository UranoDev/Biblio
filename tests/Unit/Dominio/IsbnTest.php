<?php

use App\Dominio\Isbn;
use App\Dominio\IsbnInvalido;

it('acepta un ISBN-13 bien formado', function () {
    expect(Isbn::desde('9788437604176')->valor())->toBe('9788437604176');
});

it('acepta un ISBN-10 y lo normaliza a trece dígitos', function () {
    expect(Isbn::desde('8437604176')->valor())->toBe('9788437604176');
});

it('llega al mismo resultado con el ISBN-10 y con el ISBN-13 del mismo Libro', function () {
    expect(Isbn::desde('8437604176')->esIgual(Isbn::desde('9788437604176')))->toBeTrue();
});

it('ignora los guiones y los espacios con que viene impreso', function () {
    expect(Isbn::desde(' 978-84-376-0417-6 ')->valor())->toBe('9788437604176')
        ->and(Isbn::desde('84 376 0417 6')->valor())->toBe('9788437604176');
});

it('acepta la X del dígito de control de un ISBN-10, en mayúscula o minúscula', function () {
    expect(Isbn::desde('043942089X')->valor())->toBe('9780439420891')
        ->and(Isbn::desde('043942089x')->valor())->toBe('9780439420891');
});

it('rechaza un ISBN-13 con el dígito de control equivocado', function () {
    expect(fn () => Isbn::desde('9788437604177'))->toThrow(IsbnInvalido::class);
});

it('rechaza un ISBN-10 con el dígito de control equivocado', function () {
    expect(fn () => Isbn::desde('8437604175'))->toThrow(IsbnInvalido::class);
});

it('rechaza lo que no tiene forma de ISBN', function (string $valor) {
    expect(fn () => Isbn::desde($valor))->toThrow(IsbnInvalido::class);
})->with(['', '12345', '97884376041760', 'no soy un isbn', '84376X4176', '978843760417X']);

it('dice si un texto es un ISBN sin lanzar excepción', function () {
    expect(Isbn::esValido('9788437604176'))->toBeTrue()
        ->and(Isbn::esValido('9788437604177'))->toBeFalse();
});

it('devuelve el ISBN-10 equivalente cuando existe', function () {
    expect(Isbn::desde('9788437604176')->comoDiez())->toBe('8437604176')
        ->and(Isbn::desde('9780439420891')->comoDiez())->toBe('043942089X');
});

it('no inventa un ISBN-10 para los que no empiezan con 978', function () {
    expect(Isbn::desde('9791234567896')->comoDiez())->toBeNull();
});

it('se imprime como el ISBN de trece dígitos', function () {
    expect((string) Isbn::desde('8437604176'))->toBe('9788437604176');
});
