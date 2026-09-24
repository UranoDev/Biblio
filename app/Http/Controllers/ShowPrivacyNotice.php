<?php

namespace App\Http\Controllers;

use App\Showcase\Meta;
use Illuminate\Contracts\View\View;

/**
 * The privacy notice (BIB-47): what this site collects, and what it does not.
 *
 * A static page and not a setting, because its content is a legal text
 * decided once and not something the back office edits — changing it means
 * changing this file.
 */
final class ShowPrivacyNotice extends Controller
{
    public function __invoke(): View
    {
        return view('showcase.privacy', [
            'meta' => Meta::privacy(),
        ]);
    }
}
