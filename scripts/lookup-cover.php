<?php

/**
 * Reports what Google Books and Open Library have for one ISBN, without
 * touching the application or the database: the direct address of the book on
 * Google Books, and the real pixel size of whatever cover each catalog offers.
 *
 * It exists because that is exactly what got checked by hand for Book 8 (BIB-51)
 * before deciding to lower `covers.minimum_side` — this is that same check,
 * repeatable for the next ISBN that shows up thin.
 *
 * Standalone on purpose, like the other scripts in this folder: it needs no
 * Laravel bootstrap, so it runs the same on a phone's SSH app, on the server,
 * or on a Windows machine with `php` on the PATH.
 *
 * Usage: php scripts/lookup-cover.php 9780970601971
 */
$isbn = $argv[1] ?? null;

if ($isbn === null || trim($isbn) === '') {
    fwrite(STDERR, "Uso: php scripts/lookup-cover.php <isbn>\n");
    exit(1);
}

$isbn = preg_replace('/[^0-9Xx]/', '', $isbn);

function fetch(string $url, array $headers = []): ?array
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36',
        CURLOPT_HTTPHEADER => $headers,
    ]);

    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

    curl_close($ch);

    if ($body === false) {
        return null;
    }

    return ['status' => $status, 'body' => $body, 'url' => $finalUrl];
}

function imageSize(string $url): ?array
{
    $response = fetch($url);

    if ($response === null || $response['status'] >= 400) {
        return null;
    }

    $size = @getimagesizefromstring($response['body']);

    if ($size === false) {
        return null;
    }

    return ['width' => $size[0], 'height' => $size[1], 'bytes' => strlen($response['body'])];
}

echo "ISBN {$isbn}\n";
echo str_repeat('-', 40)."\n";

// Google Books: the `vid=ISBN...` address resolves straight to the book's own
// page without going through a search result, which is the one that does not
// answer with a captcha to an anonymous request.
echo "Google Books\n";

$google = fetch("https://books.google.com/books?vid=ISBN{$isbn}&redir_esc=y");

// Google does not always answer with a redirect to the `id=` address; it
// often serves the page straight at the `vid=ISBN...` one and leaves the real
// address in `<link rel="canonical">` instead. That tag is the one thing that
// is missing on the "no edition" result, so it is what decides whether this
// found anything.
$canonical = ($google !== null && preg_match('/<link rel="canonical" href="([^"]+)"/', $google['body'], $match) === 1)
    ? html_entity_decode($match[1])
    : null;

if ($google === null || $google['status'] >= 400) {
    echo "  No contestó.\n";
} elseif ($canonical === null) {
    echo "  Sin edición para este ISBN.\n";
} else {
    echo "  {$canonical}\n";

    if (preg_match('/<title>([^<]+)<\/title>/', $google['body'], $match) === 1) {
        echo '  '.html_entity_decode(trim(str_replace(' - Google Books', '', $match[1])))."\n";
    }

    // The cover on the book's own page, the same address the site downloads
    // in practice: `books/publisher/content?id=...&printsec=frontcover...`.
    if (preg_match('/https:\/\/books\.google\.com[^"\'\s]*printsec=frontcover[^"\'\s]*/', $google['body'], $match) === 1) {
        $cover = str_replace('&amp;', '&', $match[0]);
        $size = imageSize($cover);

        echo $size === null
            ? "  Portada: no se pudo leer.\n"
            : "  Portada: {$size['width']}×{$size['height']} px ({$cover})\n";
    } else {
        echo "  Portada: no aparece en la página.\n";
    }
}

echo "\nOpen Library\n";

$record = fetch("https://openlibrary.org/isbn/{$isbn}.json");

if ($record === null || $record['status'] >= 400) {
    echo "  Sin edición para este ISBN.\n";
} else {
    $data = json_decode($record['body'], true);
    echo '  '.($data['title'] ?? '(sin título)')."\n";

    $cover = imageSize("https://covers.openlibrary.org/b/isbn/{$isbn}-L.jpg");

    echo match (true) {
        $cover === null => "  Portada: no se pudo leer.\n",
        $cover['width'] <= 1 && $cover['height'] <= 1 => "  Portada: sin portada (marcador de 1×1).\n",
        default => "  Portada: {$cover['width']}×{$cover['height']} px\n",
    };
}
