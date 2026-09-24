@props(['status', 'showcase' => false])

{{--
    Which of the three Reading states a Book is in, wherever it is shown.

    HOW THIS LOOKS IS NOT DECIDED YET. Marginalia wrote the Reading as text in
    its samples and never designed it, and the constraints rule out the two
    usual ways out: there is no accent color in this system, and a tick mark is
    the device it does not want. See CONTEXT.md, «Lo que sigue abierto».

    So this is the text the samples used, and nothing more. The whole visual is
    in this one file on purpose: whatever gets decided is a change here and
    nowhere else, and no screen writes the mark by hand.

    The Ink is the muted one of the direction, which is what reads on paper;
    under the appearance switch of the back office it keeps the zinc it had.

    The Showcase says the same three states in its own words: there the read
    date is not shown, so the two read ones read alike.

    All three states say their name, including the unread one. Marking only the
    read Books would make the unread ones and the ones nobody has looked at yet
    the same thing on screen, which is the distinction the three states exist
    for.
--}}
<span
    {{ $attributes->class(['type-section-heading text-ink-muted dark:text-zinc-400']) }}
    data-test="reading-mark"
    data-reading="{{ $status->value }}"
>{{ $showcase ? $status->publicLabel() : $status->label() }}</span>
