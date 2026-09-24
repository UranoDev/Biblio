<?php

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Features;

/**
 * The starter kit arrived in English and its screens are the ones a person sees
 * every day, so they answer to the same rule as ours: what a person reads is in
 * Spanish (CONTEXT.md § El idioma).
 *
 * Nothing here reads prose for its own sake — it walks the screens that came
 * with the kit and fails on the strings it shipped with. A `lang/es.json` that
 * loses an entry, or a locale that goes back to `en`, shows up as a failing
 * test and not as an English word somebody notices three weeks later.
 *
 * @return list<string>
 */
function starterKitEnglish(): array
{
    return [
        'Add a passkey to sign in without a password',
        'Appearance',
        'Confirm password',
        'Current password',
        'Dashboard',
        'Delete account',
        'Documentation',
        'Email address',
        'Enable 2FA',
        'Enter your email and password below to log in',
        'Enter your email to receive a password reset link',
        'Forgot your password?',
        'Log in',
        'Log out',
        'Manage your passkeys for passwordless sign-in',
        'Manage your profile and account settings',
        'Manage your two-factor authentication settings',
        'New password',
        'No passkeys yet',
        // Not «Passkeys» on its own: the browser bridge is `window.Passkeys`
        // and it travels in an Alpine attribute, which nobody reads.
        'Add passkey',
        'Give this passkey a name to help you identify it later.',
        'Platform',
        'Please enter your new password below',
        'Please verify your email address by clicking on the link we just emailed to you.',
        'Recovery codes',
        'Remember me',
        'Repository',
        'Reset password',
        'Save',
        'Security',
        'Settings',
        'This is a secure area of the application. Please confirm your password before continuing.',
        // Flux labels its own chrome, and those labels land on the screen too.
        'Toggle sidebar',
        'Toggle password visibility',
        'Two-factor authentication',
        'Update password',
        'Update the appearance settings for your account',
        'Update your name and email address',
        'or you can',
    ];
}

/** A screen with nothing left over from the starter kit, and in Spanish. */
function assertScreenIsInSpanish(TestResponse $response): void
{
    $response->assertOk();
    $response->assertSee('<html lang="es"', escape: false);

    // The scripts are out: Flux inlines an `applyAppearance` of its own, and
    // nobody reads a bundle. What is checked is what ends up on the screen.
    $screen = (string) preg_replace(
        '#<script[^>]*>.*?</script>#si',
        '',
        (string) $response->getContent(),
    );

    foreach (starterKitEnglish() as $string) {
        expect($screen)->not->toContain($string);
    }
}

it('says «Entrar» on the login screen', function () {
    assertScreenIsInSpanish($this->get(route('login')));
});

it('asks for the correo in Spanish when the contraseña is lost', function () {
    assertScreenIsInSpanish($this->get(route('password.request')));
});

it('takes the contraseña nueva in Spanish', function () {
    assertScreenIsInSpanish($this->get(route('password.reset', ['token' => 'un-token'])));
});

it('asks to confirm the contraseña in Spanish', function () {
    $this->actingAs(User::factory()->create());

    assertScreenIsInSpanish($this->get(route('password.confirm')));
});

it('asks to verify the correo in Spanish', function () {
    $this->actingAs(User::factory()->unverified()->create());

    assertScreenIsInSpanish($this->get(route('verification.notice')));
});

it('shows the sidebar and the pantalla de inicio in Spanish', function () {
    $this->actingAs(User::factory()->create());

    assertScreenIsInSpanish($this->get(route('dashboard')));
});

it('shows the perfil in Spanish', function () {
    $this->actingAs(User::factory()->create());

    assertScreenIsInSpanish($this->get(route('profile.edit')));
});

it('shows the apariencia in Spanish', function () {
    $this->actingAs(User::factory()->create());

    assertScreenIsInSpanish($this->get(route('appearance.edit')));
});

it('shows the seguridad in Spanish, with its passkeys and its 2FA', function () {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);
    Features::passkeys([
        'confirmPassword' => true,
    ]);

    $this->actingAs(User::factory()->create())
        ->withSession(['auth.password_confirmed_at' => time()]);

    assertScreenIsInSpanish($this->get(route('security.edit')));
});

it('asks for the 2FA code in Spanish', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    assertScreenIsInSpanish($this->get(route('two-factor.login')));
});

it('complains in Spanish when the correo is missing', function () {
    $this->post(route('login.store'), ['email' => '', 'password' => ''])
        ->assertSessionHasErrors(['email' => 'Falta el correo.']);
});

it('complains in Spanish when the credentials do not match', function () {
    $this->post(route('login.store'), [
        'email' => 'nadie@example.com',
        'password' => 'no-es-la-contrasena',
    ])->assertSessionHasErrors(['email' => 'Esos datos no coinciden con ninguna cuenta.']);
});
