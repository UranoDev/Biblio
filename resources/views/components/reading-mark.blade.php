@props(['book', 'showcase' => false])

{{--
    The line above the title: which of the three Reading states a Book is in,
    and, in the back office, the year it was read (BIB-38).

    The stamp over the Cover answers «¿lo leí?» from across the grid; this line
    answers «¿cuándo?», and that is why the two are not the same thing written
    twice. A Book read without a date does not say «Leído» here and «Leído»
    again underneath: the line says it once and the stamp is two centimetres
    away.

    The Ink is the muted one, like the rest of the labels of the sheet. The
    Link ochre was the other candidate and it is the one colour of the system,
    so a line set in it reads as a link and invites a click it has nothing to
    do with; full Ink was tried too and it takes the eye before the title does.

    The whole state, the three of them, is written here and in the stamp beside
    it, and nowhere else: no screen draws the mark by hand.
--}}
<span
    {{ $attributes->class(['type-section-heading text-ink-muted dark:text-zinc-400']) }}
    data-test="reading-mark"
    data-reading="{{ $book->status->value }}"
>{{ $showcase ? $book->status->publicLabel() : $book->status->markLabel($book->read_on?->year) }}</span>
