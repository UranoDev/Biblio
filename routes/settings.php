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

    /*
     * The images with which a Quote gets shared (BIB-31). It is where the count
     * of what is owed lives, and where the queue says it stopped for want of
     * credits — a stop that is correct and silent without a screen (ADR-009).
     */
    Route::livewire('settings/quote-images', 'pages::settings.quote-images')->name('quote-images');

    /*
     * The jobs the queue gave up on (BIB-40). The Dashboard prints how many
     * there are and this is the list behind that number.
     */
    Route::livewire('settings/failed-jobs', 'pages::settings.failed-jobs')->name('failed-jobs');

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
