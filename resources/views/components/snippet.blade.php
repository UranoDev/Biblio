@use('App\Search\Snippet')

@props(['text', 'words'])

{{--
    A fragment of a text with the words that were searched marked inside it.

    Everything below is on one line on purpose. The pieces of the fragment are
    drawn one after another with nothing between them, and a line break in this
    file would be a space in the middle of a word — «a Com ala» instead of «a
    Comala». It is the one place in the views where the formatting is load
    bearing, so it is written down here and not left to be rediscovered.

    The pieces arrive as data and get escaped like any other text: the Quote was
    typed by a person, and the only markup that reaches the browser is the mark
    this file puts around them.
--}}
<span {{ $attributes }}>@foreach (Snippet::of($text, $words)->fragments() as $fragment)@if ($fragment->found)<mark class="rounded-xs bg-amber-200 px-0.5 text-zinc-900">{{ $fragment->text }}</mark>@else{{ $fragment->text }}@endif@endforeach</span>
