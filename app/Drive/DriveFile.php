<?php

namespace App\Drive;

/**
 * One image in the Drive folder, with what is needed to decide how to fetch it.
 */
final readonly class DriveFile
{
    public function __construct(
        public string $id,
        public string $name,
        public string $mimeType,
        public ?int $size,
        public ?string $thumbnailLink,
    ) {}

    /** @param  array<string, mixed>  $file  one entry of `files.list`. */
    public static function fromApi(array $file): self
    {
        return new self(
            id: (string) $file['id'],
            name: (string) ($file['name'] ?? ''),
            mimeType: (string) ($file['mimeType'] ?? ''),
            size: isset($file['size']) ? (int) $file['size'] : null,
            thumbnailLink: isset($file['thumbnailLink']) ? (string) $file['thumbnailLink'] : null,
        );
    }
}
