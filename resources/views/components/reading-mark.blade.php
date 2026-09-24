@props(['status'])

{{--
    Which of the three Reading states a Book is in, on the Shelf grid.

    HOW THIS LOOKS IS NOT DECIDED YET. Marginalia wrote the Reading as text in
    its samples and never designed it, and the constraints rule out the two
    usual ways out: there is no accent color in this system, and a tick mark is
    the device it does not want. See CONTEXT.md, «Lo que sigue abierto».

    So this is the text the samples used, and nothing more. The whole visual is
    in this one file on purpose: whatever gets decided is a change here and
    nowhere else, and no screen writes the mark by hand.

    All three states say their name, including the unread one. Marking only the
    read Books would make the unread ones and the ones nobody has looked at yet
    the same thing on screen, which is the distinction the three states exist
    for.
--}}
<span
    {{ $attributes->class(['type-section-heading text-zinc-500 dark:text-zinc-400']) }}
    data-test="reading-mark"
    data-reading="{{ $status->value }}"
>{{ $status->label() }}</span>
