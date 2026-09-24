<?php

/*
 * The stamp of a read Book is drawn over the Cover, so its ink is not measured
 * against the paper of the page: it is measured against the field the stamp
 * brings with it, and against the darkest that field can get (BIB-38).
 *
 * designToken(), relativeLuminance() and contrastRatio() live in tests/Pest.php.
 */

it('guarda el sello como tokens de la dirección', function (string $name) {
    expect(designToken($name))->not->toBe('');
})->with([
    'color-stamp-ink',
    'color-stamp-field',
    'color-stamp-field-floor',
    'stamp-field-alpha',
]);

it('deja la tinta del sello legible sobre su campo', function (string $field) {
    expect(contrastRatio(designToken('color-stamp-ink'), designToken($field)))
        ->toBeGreaterThanOrEqual(4.5);
})->with(['color-stamp-field', 'color-stamp-field-floor']);

it('mide el piso del campo contra la Portada más oscura que puede tocarle', function () {
    // The field is laid down translucent, so over a black photograph it lands
    // on its darkest. That composite is what the floor token holds, and the
    // contrast above is only worth what this equality is worth.
    $alpha = (float) designToken('stamp-field-alpha');

    $channels = str_split(ltrim(designToken('color-stamp-field'), '#'), 2);

    $floor = '#'.implode('', array_map(
        fn (string $pair): string => str_pad(
            dechex((int) round((int) hexdec($pair) * $alpha)),
            2,
            '0',
            STR_PAD_LEFT,
        ),
        $channels,
    ));

    expect($floor)->toBe(strtolower(designToken('color-stamp-field-floor')));
});
