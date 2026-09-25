<?php

namespace App\Console\Commands;

use App\Covers\Image;
use App\Covers\InvalidImage;
use App\Models\Book;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Converts existing JPEG thumbnails to WebP (BIB-66).
 *
 * New thumbnails are already saved as WebP; this command migrates the ones
 * that were stored before the change. It reads the original stored image,
 * re-scales it through Image::scaled() — which now outputs WebP — writes
 * the new file, updates the column, and deletes the old JPEG. Books whose
 * thumbnail already ends in .webp are skipped.
 */
final class ConvertThumbnailsToWebp extends Command
{
    protected $signature = 'covers:convert-thumbnails-to-webp
        {--pretend : lista los libros sin convertir nada}';

    protected $description = 'Convierte las miniaturas de Portada de JPEG a WebP.';

    public function handle(): int
    {
        $thumbnailWidth = (int) config('covers.thumbnail_width', 320);

        $books = Book::query()
            ->whereNotNull('cover_thumbnail_path')
            ->whereNotNull('cover_path')
            ->get()
            ->filter(fn (Book $book) => ! str_ends_with((string) $book->cover_thumbnail_path, '.webp'));

        if ($books->isEmpty()) {
            $this->components->info('Todas las miniaturas ya están en WebP.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            '%d %s por convertir.',
            $books->count(),
            $books->count() === 1 ? 'miniatura queda' : 'miniaturas quedan',
        ));

        if ($this->option('pretend')) {
            foreach ($books as $book) {
                $this->line(sprintf('  %d — %s (%s)', $book->id, $book->title, $book->cover_thumbnail_path));
            }

            $this->components->warn('No se convirtió nada: corrió con --pretend.');

            return self::SUCCESS;
        }

        $converted = 0;
        $failed = 0;

        foreach ($books as $book) {
            $binary = Storage::disk()->get($book->cover_path);

            if ($binary === null) {
                $this->components->warn(sprintf('%d — original no encontrado: %s', $book->id, $book->cover_path));
                $failed++;

                continue;
            }

            try {
                $image = Image::from($binary);
                $webp = $image->scaled($thumbnailWidth);
            } catch (InvalidImage $e) {
                $this->components->warn(sprintf('%d — imagen inválida: %s', $book->id, $e->getMessage()));
                $failed++;

                continue;
            }

            $dir = dirname((string) $book->cover_path);
            $stem = Str::beforeLast(basename((string) $book->cover_thumbnail_path), '.');
            $newPath = "{$dir}/{$stem}.webp";

            Storage::disk()->put($newPath, $webp);

            $oldPath = $book->cover_thumbnail_path;

            $book->forceFill(['cover_thumbnail_path' => $newPath])->save();

            Storage::disk()->delete($oldPath);

            $this->line(sprintf('  ✓ %d — %s', $book->id, $book->title));
            $converted++;
        }

        $this->components->info(sprintf('%d convertidas, %d fallidas.', $converted, $failed));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
