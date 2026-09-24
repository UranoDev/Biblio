<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');

    Route::livewire('settings/ai-model', 'pages::settings.ai-model')->name('ai-model.edit');

    /*
     * The mass regeneration (BIB-21). The name is `regenerate` and not
     * `regenerate.edit` because the AiModel screen asks for it by that name to
     * decide whether it has anywhere to send the person.
     */
    Route::livewire('settings/regenerate', 'pages::settings.regenerate')->name('regenerate');

    Route::livewire('settings/security', 'pages::settings.security')
        ->middleware([
            'password.confirm',
        ])
        ->name('security.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
