@props(['rows' => 3])

{{--
    A grid that opens on its first rows, with a control that reveals the rest
    (BIB-60).

    How many Books fit in three rows is not a number: it is four Covers per row
    at one width and five at another, and it changes again the day a breakpoint
    moves. So nothing is counted here — the browser reads the top of each card
    already laid out and keeps the ones that share the first three, which is the
    same question the eye asks.

    Everything comes down in the HTML and nothing is fetched on the click. The
    Showcase is a Blade page with no Livewire and no POST, and that is the point
    of it (ADR-003): the public face answers GET and nothing else, so revealing
    the rest cannot be a request. The reveal is the browser undoing what it hid
    a moment earlier.

    Which means: without scripts the whole grid is there and the control is not,
    because the control is unhidden by the same script that hides the cards.
    Nothing is behind a button that may never work.

    On a narrow screen the grid goes down to two or three columns and three rows
    are six Books out of eighty — a sample too small to be worth a control, and
    a phone is scrolled anyway. There the page stays as it was.
--}}
<div class="flex flex-col gap-8" data-rows="{{ $rows }}" data-test="first-rows">
    {{ $slot }}

    <button
        type="button"
        hidden
        data-more
        data-test="first-rows-more"
        class="type-section-heading self-start text-link underline decoration-rule decoration-1 underline-offset-4 hover:decoration-link"
    >Cargar más</button>
</div>

<script>
    (function () {
        var block = document.currentScript.previousElementSibling;
        var grid = block.querySelector('ul');
        var more = block.querySelector('[data-more]');

        if (!grid || !more) {
            return;
        }

        var cards = Array.prototype.slice.call(grid.children);
        var rows = Number(block.dataset.rows);

        // `md`, where the grid is already four columns wide.
        var wide = window.matchMedia('(min-width: 48rem)');

        var open = false;
        var from = cards.length;

        // How many cards share the first `rows` tops. Cards of one row of a CSS
        // grid all start at the same height, so the distinct tops are the rows —
        // whatever the column count is, and whatever each row ends up measuring.
        function firstRows() {
            var tops = [];

            for (var i = 0; i < cards.length; i++) {
                var top = Math.round(cards[i].offsetTop);

                if (tops.indexOf(top) === -1) {
                    if (tops.length === rows) {
                        return i;
                    }

                    tops.push(top);
                }
            }

            return cards.length;
        }

        function revealAll() {
            for (var i = 0; i < cards.length; i++) {
                cards[i].hidden = false;
            }

            more.hidden = true;
        }

        // Measuring asks for everything in place, so this puts the grid back
        // whole before reading it — which is also what it does on a narrow
        // screen, and what it does when the rows turn out to hold the lot.
        function collapse() {
            if (open) {
                return;
            }

            revealAll();

            if (!wide.matches) {
                return;
            }

            from = firstRows();

            if (from >= cards.length) {
                return;
            }

            for (var i = from; i < cards.length; i++) {
                cards[i].hidden = true;
            }

            more.hidden = false;
        }

        more.addEventListener('click', function () {
            var next = cards[from];

            open = true;
            revealAll();

            // The button that held the focus is gone, so the focus goes to the
            // first Book that was not there a moment ago; whoever walks the page
            // by keyboard carries on where the reading does.
            var link = next && next.querySelector('a');

            if (link) {
                link.focus();
            }
        });

        // A resize changes the column count, and with it what three rows are.
        // Once the whole grid is out it stays out: it was asked for.
        var pending;

        window.addEventListener('resize', function () {
            clearTimeout(pending);
            pending = setTimeout(collapse, 150);
        });

        collapse();
    })();
</script>
