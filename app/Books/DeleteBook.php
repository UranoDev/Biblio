<?php

namespace App\Books;

use App\Models\Book;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes a Book and everything that only exists because of it.
 *
 * The database takes care of the rows: the Quotes and the assigned Categories
 * go with it (cascade), and a Category it suggested stays, without the
 * reference (null on delete). What the database does not see are the files on
 * the disk —the Cover, its thumbnail, the card of the Book and the images of
 * each Quote— and those are erased here.
 *
 * The files go after the rows and not before: if the delete fails, the Book is
 * still there with all of its images, instead of a Book pointing at files that
 * no longer exist.
 */
final class DeleteBook
{
    public function delete(Book $book): void
    {
        $files = $this->filesOf($book);

        DB::transaction(fn () => $book->delete());

        foreach ($files as $file) {
            Storage::disk()->delete($file);
        }
    }

    /** @return list<string> */
    private function filesOf(Book $book): array
    {
        $files = [
            $book->cover_path,
            $book->cover_thumbnail_path,
            $book->share_image_path,
        ];

        foreach ($book->quotes()->get(['id', 'card_image_path', 'story_image_path']) as $quote) {
            $files[] = $quote->card_image_path;
            $files[] = $quote->story_image_path;
        }

        return array_values(array_filter($files, fn (?string $file): bool => $file !== null && $file !== ''));
    }
}
