<x-showcase.layout :meta="$meta" :browse="false">
    <header class="flex max-w-prose flex-col gap-4">
        <h1 class="text-3xl font-bold tracking-tight md:text-4xl">Términos de uso</h1>
    </header>

    <div class="flex max-w-prose flex-col gap-8">
        <section class="flex flex-col gap-2">
            <h2 class="text-xl font-bold tracking-tight">Qué es este sitio</h2>
            <p>
                {{ config('app.name') }} es un catálogo personal de lectura: libros,
                categorías y citas extraídas de ellos, con descripciones propias o
                generadas con IA.
            </p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-xl font-bold tracking-tight">Descripciones generadas por IA</h2>
            <p>
                Las descripciones marcadas como generadas por IA (icono ✨ «IA») pueden
                contener errores o imprecisiones. No las tomes como fuente definitiva de
                información sobre el libro — verifica contra la fuente original si la vas
                a usar para algo importante.
            </p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-xl font-bold tracking-tight">Citas de libros</h2>
            <p>
                Las citas que aparecen en el sitio son fragmentos breves de la obra
                citada, con su autor y número de página. Se incluyen con fines de
                comentario y catálogo personal de lectura, no como reproducción del
                libro completo.
            </p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-xl font-bold tracking-tight">Uso permitido</h2>
            <p>
                Puedes consultar el catálogo y compartir el enlace de cualquier libro,
                categoría o cita usando los botones de compartir del sitio. No está
                permitido extraer o reproducir el contenido del sitio a gran escala.
            </p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-xl font-bold tracking-tight">Disponibilidad</h2>
            <p>
                El sitio se ofrece tal como está. No se garantiza que esté disponible en
                todo momento ni que el catálogo esté libre de errores.
            </p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-xl font-bold tracking-tight">Propiedad</h2>
            <p>
                El código y el diseño del sitio pertenecen a Urano. El contenido de cada
                libro citado pertenece a su autor y editorial originales.
            </p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-xl font-bold tracking-tight">Cambios a estos términos</h2>
            <p>Si estos términos cambian, la fecha de la última actualización se muestra abajo.</p>
        </section>

        <p class="text-sm text-ink-muted">Última actualización: 24 de septiembre de 2026.</p>
    </div>
</x-showcase.layout>
