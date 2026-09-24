<x-showcase.layout :meta="$meta" :browse="false">
    <header class="flex max-w-prose flex-col gap-4">
        <h1 class="text-3xl font-bold tracking-tight md:text-4xl">Aviso de privacidad</h1>
    </header>

    <div class="flex max-w-prose flex-col gap-8">
        <section class="flex flex-col gap-2">
            <h2 class="text-xl font-bold tracking-tight">Quién es responsable de este sitio</h2>
            <p>
                {{ config('app.name') }} (biblio.urano.dev) es operado por Urano. Para
                cualquier duda sobre este aviso, escribe a
                <a href="mailto:biblio@urano.dev" class="text-link">biblio@urano.dev</a>.
            </p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-xl font-bold tracking-tight">Qué datos se recopilan</h2>
            <p>
                Este sitio no pide registro, no tiene formularios públicos y no coloca
                cookies de terceros ni de rastreo. Si visitas el catálogo, ver un libro,
                una categoría o una cita, no queda ningún dato personal tuyo almacenado.
            </p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-xl font-bold tracking-tight">Generación de descripciones con IA</h2>
            <p>
                Algunas descripciones de libros se generan con modelos de inteligencia
                artificial. La IA puede equivocarse: el lector es responsable de
                verificar cualquier información antes de darla por cierta.
            </p>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-xl font-bold tracking-tight">Cambios a este aviso</h2>
            <p>Si este aviso cambia, la fecha de la última actualización se muestra abajo.</p>
        </section>

        <p class="text-sm text-ink-muted">Última actualización: 24 de septiembre de 2026.</p>
    </div>
</x-showcase.layout>
