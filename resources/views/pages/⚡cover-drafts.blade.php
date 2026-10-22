<?php

use App\Drive\GoogleDrive;
use App\Jobs\ReadCoverDraft;
use App\Models\CoverDraft;
use App\Models\DriveImport;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * CoverDrafts: the photographs that arrived from the Drive folder and wait to
 * be reviewed (BIB-74).
 *
 * Each one opens ManualEntry already filled in with what was read off it, and
 * the Book is registered there, so a draft goes through the same validation as
 * a photograph uploaded by hand.
 */
new #[Title('Fotos por revisar')] class extends Component
{
    /** @return Collection<int, CoverDraft> */
    #[Computed]
    public function drafts(): Collection
    {
        return CoverDraft::query()->oldest()->get();
    }

    #[Computed]
    public function import(): ?DriveImport
    {
        return DriveImport::current();
    }

    #[Computed]
    public function configured(): bool
    {
        return GoogleDrive::isConfigured();
    }

    /** Asks the AI again, for a photograph it could not read. */
    public function readAgain(int $id): void
    {
        $draft = CoverDraft::query()->find($id);

        if (! $draft instanceof CoverDraft || ! $draft->hasPhoto()) {
            return;
        }

        $draft->forceFill(['read_at' => null, 'read_failure' => null])->save();

        ReadCoverDraft::dispatch($draft);

        unset($this->drafts);
    }

    public function discard(int $id): void
    {
        CoverDraft::query()->find($id)?->delete();

        unset($this->drafts);
    }
}; ?>

<div class="flex w-full max-w-3xl flex-col gap-8">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">Fotos por revisar</flux:heading>

        <flux:text>
            Las fotos de portadas que llegaron de la carpeta del Drive. Revisa los datos de cada una y registra el Libro.
        </flux:text>

        <flux:text class="text-sm" data-test="import-state">
            @if (! $this->configured)
                La carpeta del Drive no está configurada. Las fotos se pueden subir en Captura manual.
            @elseif ($this->import === null)
                La carpeta del Drive no se ha revisado todavía.
            @elseif ($this->import->hasFailed())
                La última revisión de la carpeta falló {{ $this->import->last_failed_at->diffForHumans() }}.
            @else
                Última revisión de la carpeta: {{ $this->import->last_checked_at->diffForHumans() }}.
            @endif
        </flux:text>
    </div>

    <flux:separator />

    @if ($this->drafts->isEmpty())
        <flux:text data-test="empty">No hay fotos por revisar.</flux:text>
    @else
        <ul class="flex flex-col divide-y divide-zinc-200 dark:divide-zinc-700" data-test="drafts">
            @foreach ($this->drafts as $draft)
                <li class="flex gap-4 py-4" wire:key="draft-{{ $draft->getKey() }}" data-test="draft-{{ $draft->getKey() }}">
                    <div class="w-20 shrink-0">
                        @if ($draft->hasPhoto())
                            <img
                                src="{{ route('cover-draft.photo', $draft) }}"
                                alt="Foto de la portada"
                                class="cover-edge aspect-2/3 w-full rounded-lg object-cover"
                                loading="lazy"
                            >
                        @else
                            <div class="flex aspect-2/3 w-full items-center justify-center rounded-lg border border-dashed border-rule">
                                <flux:icon.camera class="size-6 text-ink-muted" />
                            </div>
                        @endif
                    </div>

                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <div class="flex flex-col gap-0.5">
                            @if (! $draft->hasPhoto())
                                <flux:heading>{{ $draft->drive_file_name }}</flux:heading>
                                <flux:text class="text-sm">No se pudo traer la foto del Drive.</flux:text>
                            @elseif (! $draft->isRead())
                                <flux:heading>{{ $draft->drive_file_name }}</flux:heading>
                                <flux:text class="text-sm">Sin leer todavía.</flux:text>
                            @elseif ($draft->wasUnreadable())
                                <flux:heading>{{ $draft->drive_file_name }}</flux:heading>
                                <flux:text class="text-sm">No se pudieron leer los datos de la foto.</flux:text>
                            @else
                                <flux:heading>{{ $draft->title ?? 'Sin título' }}</flux:heading>

                                @if ($draft->authors)
                                    <flux:text class="text-sm">{{ implode(', ', $draft->authors) }}</flux:text>
                                @endif

                                <flux:text class="text-sm">
                                    {{ $draft->from_source ? 'Datos de la Fuente, por el ISBN de la foto.' : 'Datos leídos de la foto.' }}
                                </flux:text>
                            @endif

                            @if ($draft->hasPhoto() && $draft->hasFailed())
                                <flux:text class="text-sm" data-test="still-in-folder">
                                    La foto sigue en la carpeta del Drive: no se pudo mover a Procesados.
                                </flux:text>
                            @endif
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @if ($draft->hasPhoto())
                                <flux:button
                                    size="sm"
                                    variant="primary"
                                    :href="route('manual-entry', ['draft' => $draft->getKey()])"
                                    wire:navigate
                                    data-test="review-{{ $draft->getKey() }}"
                                >
                                    Revisar y registrar
                                </flux:button>
                            @endif

                            @if ($draft->wasUnreadable())
                                <flux:button size="sm" variant="outline" wire:click="readAgain({{ $draft->getKey() }})" data-test="read-again-{{ $draft->getKey() }}">
                                    Volver a leer
                                </flux:button>
                            @endif

                            <flux:button
                                size="sm"
                                variant="outline"
                                wire:click="discard({{ $draft->getKey() }})"
                                wire:confirm="¿Descartar esta foto? No se registra ningún Libro."
                                data-test="discard-{{ $draft->getKey() }}"
                            >
                                Descartar
                            </flux:button>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
