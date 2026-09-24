<?php

namespace App\Http\Controllers;

use App\Showcase\Meta;
use Illuminate\Contracts\View\View;

/**
 * The terms of use (BIB-47): what this site is, and what a visitor may do
 * with it.
 *
 * A static page and not a setting, because its content is a legal text
 * decided once and not something the back office edits — changing it means
 * changing this file.
 */
final class ShowTermsOfUse extends Controller
{
    public function __invoke(): View
    {
        return view('showcase.terms', [
            'meta' => Meta::terms(),
        ]);
    }
}
