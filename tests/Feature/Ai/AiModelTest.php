<?php

use App\Ai\AiModel;
use App\Models\AiSetting;

it('lee el AiModel elegido de la base', function () {
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $chosen = AiModel::chosen();

    expect($chosen)->not->toBeNull()
        ->and($chosen->provider)->toBe('gemini')
        ->and($chosen->name)->toBe('gemini-2.5-flash');
});

it('no hay AiModel elegido mientras nadie lo haya elegido', function () {
    expect(AiModel::chosen())->toBeNull();
});

it('no elige el AiModel leyendo el entorno', function () {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(app_path(), RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        expect(file_get_contents($file->getPathname()))
            ->not->toContain('env(', "El archivo [{$file->getFilename()}] lee el entorno; el Modelo se elige en pantalla y se guarda en la base.");
    }
});
