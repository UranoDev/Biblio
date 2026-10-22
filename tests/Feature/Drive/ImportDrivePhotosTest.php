<?php

use App\Ai\CoverReader;
use App\Intake\ReadCover;
use App\Jobs\ImportDrivePhotos;
use App\Jobs\ReadCoverDraft;
use App\Models\CoverDraft;
use App\Models\DriveImport;
use App\Sources\LookupResult;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Queue::fake();
    Storage::fake();
    configureDrive();

    unset($GLOBALS['driveHolds']);
});

/**
 * Drive as the import sees it: the folder holds `$files`, each one downloads
 * as a test image, and moving answers with `$moveStatus`.
 *
 * @param  list<array<string, mixed>>  $files
 */
function driveHolds(array $files, int $moveStatus = 200, int $downloadStatus = 200): void
{
    // Faked once per test and read from here, because a second Http::fake()
    // does not replace the first one: the first stub that answers wins.
    $first = ! isset($GLOBALS['driveHolds']);

    $GLOBALS['driveHolds'] = compact('files', 'moveStatus', 'downloadStatus');

    if (! $first) {
        return;
    }

    Http::fake(function (Request $request) {
        ['files' => $files, 'moveStatus' => $moveStatus, 'downloadStatus' => $downloadStatus] = $GLOBALS['driveHolds'];
        $url = $request->url();

        return match (true) {
            str_contains($url, 'oauth2.googleapis.com') => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            str_contains($url, 'lh3.googleusercontent.com') => Http::response(testImage(), $downloadStatus),
            $request->method() === 'PATCH' => Http::response(['id' => 'x'], $moveStatus),
            str_contains($url, 'alt=media') => Http::response(testImage(), $downloadStatus),
            default => Http::response(['files' => $files]),
        };
    });
}

/** One pass of the import, run here: the queue is faked. */
function runDriveImport(): void
{
    app()->call([new ImportDrivePhotos, 'handle']);
}

/** @return array<string, string> */
function aDriveJpeg(string $id = 'photo-1'): array
{
    return ['id' => $id, 'name' => "{$id}.jpg", 'mimeType' => 'image/jpeg', 'size' => '20000'];
}

it('deja un borrador por foto, con la foto guardada, y la manda a leer', function () {
    driveHolds([aDriveJpeg()]);

    runDriveImport();

    $draft = CoverDraft::sole();

    expect($draft->drive_file_id)->toBe('photo-1')
        ->and($draft->drive_file_name)->toBe('photo-1.jpg')
        ->and($draft->hasPhoto())->toBeTrue()
        ->and($draft->hasFailed())->toBeFalse();

    Storage::disk()->assertExists($draft->photo_path);
    Queue::assertPushed(ReadCoverDraft::class, fn (ReadCoverDraft $job) => $job->draft->is($draft));
});

it('mueve la foto a la carpeta de procesadas', function () {
    driveHolds([aDriveJpeg()]);

    runDriveImport();

    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && str_contains($request->url(), '/files/photo-1?')
        && str_contains($request->url(), 'addParents=done')
        && str_contains($request->url(), 'removeParents=inbox'));
});

it('usa la miniatura del Drive para una foto HEIC', function () {
    driveHolds([['id' => 'heic', 'name' => 'IMG_1.HEIC', 'mimeType' => 'image/heic', 'size' => '2000000', 'thumbnailLink' => 'https://lh3.googleusercontent.com/t=s220']]);

    runDriveImport();

    expect(CoverDraft::sole()->hasPhoto())->toBeTrue();
    Http::assertSent(fn (Request $request) => $request->url() === 'https://lh3.googleusercontent.com/t=s1600');
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'alt=media'));
});

it('usa la miniatura cuando la foto pesa más de lo que puede pesar una Portada', function () {
    driveHolds([['id' => 'big', 'name' => 'big.jpg', 'mimeType' => 'image/jpeg', 'size' => (string) (8 * 1024 * 1024), 'thumbnailLink' => 'https://lh3.googleusercontent.com/t=s220']]);

    runDriveImport();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'lh3.googleusercontent.com'));
});

it('no duplica la foto cuando moverla falló: la vuelve a mover y ya', function () {
    driveHolds([aDriveJpeg()], moveStatus: 500);

    runDriveImport();

    expect(CoverDraft::sole()->hasFailed())->toBeTrue()
        ->and(CoverDraft::sole()->failure)->toContain('processed folder');

    driveHolds([aDriveJpeg()]);

    runDriveImport();

    expect(CoverDraft::query()->count())->toBe(1)
        ->and(CoverDraft::sole()->hasFailed())->toBeFalse();
    Queue::assertPushed(ReadCoverDraft::class, 1);
    expect(Http::recorded(fn (Request $request) => str_contains($request->url(), 'alt=media')))->toHaveCount(1);
});

it('deja el borrador sin foto cuando no la pudo traer, y la trae en la siguiente pasada', function () {
    driveHolds([aDriveJpeg()], downloadStatus: 500);

    runDriveImport();

    $draft = CoverDraft::sole();

    expect($draft->hasPhoto())->toBeFalse()
        ->and($draft->hasFailed())->toBeTrue();
    Http::assertNotSent(fn (Request $request) => $request->method() === 'PATCH');
    Queue::assertNothingPushed();

    driveHolds([aDriveJpeg()]);

    runDriveImport();

    expect($draft->fresh()->hasPhoto())->toBeTrue()
        ->and($draft->fresh()->hasFailed())->toBeFalse();
});

it('anota la revisión de la carpeta', function () {
    driveHolds([]);

    runDriveImport();

    expect(DriveImport::current()->last_checked_at)->not->toBeNull()
        ->and(DriveImport::current()->hasFailed())->toBeFalse();
});

it('anota por qué no pudo revisar la carpeta, sin fallar el trabajo', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant'], 400),
    ]);

    runDriveImport();

    expect(DriveImport::current()->hasFailed())->toBeTrue()
        ->and(DriveImport::current()->last_failure)->toContain('invalid_grant')
        ->and(CoverDraft::query()->count())->toBe(0);
});

it('no hace nada mientras el Drive no está configurado', function () {
    config(['drive.folder' => null]);

    runDriveImport();

    expect(DriveImport::current())->toBeNull();
    Http::assertNothingSent();
});

it('la lectura llena el borrador con lo que la IA leyó', function () {
    CoverReader::fake([['title' => 'Pedro Páramo', 'authors' => ['Juan Rulfo'], 'publisher' => 'RM', 'isbn' => '']]);
    sourcesAnswer(LookupResult::withoutRecord());

    $draft = coverDraft();

    (new ReadCoverDraft($draft))->handle(app(ReadCover::class));

    $draft->refresh();

    expect($draft->isRead())->toBeTrue()
        ->and($draft->wasUnreadable())->toBeFalse()
        ->and($draft->title)->toBe('Pedro Páramo')
        ->and($draft->authors)->toBe(['Juan Rulfo'])
        ->and($draft->publisher)->toBe('RM')
        ->and($draft->from_source)->toBeFalse();
});

it('la lectura anota que no pudo leer la foto, sin fallar el trabajo', function () {
    CoverReader::fake([['title' => '', 'authors' => [], 'publisher' => '', 'isbn' => '']]);

    $draft = coverDraft();

    (new ReadCoverDraft($draft))->handle(app(ReadCover::class));

    expect($draft->fresh()->isRead())->toBeTrue()
        ->and($draft->fresh()->wasUnreadable())->toBeTrue();
});
