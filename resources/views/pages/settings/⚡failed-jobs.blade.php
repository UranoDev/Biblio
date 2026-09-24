<?php

use App\Dashboard\FailedJobs;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The jobs the queue gave up on (BIB-40).
 *
 * The Categories, the Description and the images of the Quotes all arrive
 * through the queue, and all three fail without saying so anywhere. This is
 * where the number on the Dashboard leads, and what it shows is the three
 * things a row of `failed_jobs` can answer: which job, when, and what it said.
 *
 * It only reads. Putting a job back in line is `php artisan queue:retry`, and
 * it is run by somebody who first looked at why it failed.
 */
new #[Title('Trabajos fallidos')] class extends Component
{
    /** @return list<\App\Dashboard\FailedJob> */
    #[Computed]
    public function jobs(): array
    {
        return FailedJobs::recent();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout
        heading="Trabajos fallidos"
        subheading="Lo que la cola dejó de intentar. Por ahí pasan las categorías, las descripciones y las imágenes de las citas."
    >
        <div class="flex flex-col gap-6">
            @if ($this->jobs === [])
                <flux:text data-test="none">Ningún trabajo falló.</flux:text>
            @else
                <flux:text data-test="count">{{ count($this->jobs).(count($this->jobs) === 1 ? ' trabajo fallido.' : ' trabajos fallidos.') }}</flux:text>

                <ul class="flex flex-col gap-5" data-test="jobs">
                    @foreach ($this->jobs as $job)
                        <li class="flex flex-col gap-1">
                            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                <span class="font-mono text-sm">{{ $job->name }}</span>

                                @if ($job->failedAt)
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $job->failedAt->translatedFormat('d M Y, H:i') }}
                                    </span>
                                @endif
                            </div>

                            <flux:text size="sm">{{ $job->reason }}</flux:text>
                        </li>
                    @endforeach
                </ul>

                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400" data-test="how-to-retry">
                    Para volver a encolarlos: <span class="font-mono">php artisan queue:retry all</span>
                </flux:text>
            @endif
        </div>
    </x-pages::settings.layout>
</section>
