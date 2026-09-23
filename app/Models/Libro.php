<?php

namespace App\Models;

use App\Enums\EstadoLectura;
use App\Enums\Fuente;
use App\Enums\Procedencia;
use App\Enums\Situacion;
use Database\Factories\LibroFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Una edición concreta, identificada por su ISBN: la unidad que se registra,
 * que se tiene o que se quiere tener.
 *
 * @property int $id
 * @property string|null $isbn
 * @property string $titulo
 * @property list<string> $autores
 * @property string|null $editorial
 * @property int|null $anio
 * @property string|null $edicion
 * @property string|null $descripcion
 * @property Procedencia|null $descripcion_procedencia
 * @property Fuente|null $fuente
 * @property string|null $portada_ruta
 * @property string|null $portada_origen_url
 * @property string|null $portada_miniatura_ruta
 * @property Situacion $situacion
 * @property bool $leido
 * @property Carbon|null $fecha_lectura
 * @property string|null $apunte
 * @property string|null $prestado_a
 * @property Carbon|null $prestado_el
 * @property bool $oculto
 * @property string|null $categorias_proveedor
 * @property string|null $categorias_modelo
 * @property string|null $descripcion_proveedor
 * @property string|null $descripcion_modelo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Cita> $citas
 * @property-read Collection<int, Categoria> $categorias
 */
class Libro extends Model
{
    /** @use HasFactory<LibroFactory> */
    use HasFactory;

    protected $table = 'libros';

    protected $guarded = ['id'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'autores' => 'array',
            'descripcion_procedencia' => Procedencia::class,
            'fuente' => Fuente::class,
            'situacion' => Situacion::class,
            'leido' => 'boolean',
            'fecha_lectura' => 'date',
            'prestado_el' => 'date',
            'oculto' => 'boolean',
        ];
    }

    /** @return HasMany<Cita, $this> */
    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    /** @return BelongsToMany<Categoria, $this> */
    public function categorias(): BelongsToMany
    {
        return $this->belongsToMany(Categoria::class, 'categoria_libro');
    }

    /**
     * En cuál de los tres estados de Lectura está el Libro.
     *
     * Leído sin fecha no es lo mismo que no leído, así que el estado no se
     * puede deducir de la fecha sola.
     */
    public function estadoLectura(): EstadoLectura
    {
        if (! $this->leido) {
            return EstadoLectura::NoLeido;
        }

        return $this->fecha_lectura === null
            ? EstadoLectura::LeidoSinFecha
            : EstadoLectura::LeidoConFecha;
    }

    /**
     * La Portada se guarda en el disco configurado, no se enlaza a la Fuente
     * (ADR-004). Un Libro sin Portada es un estado normal: la descarga pudo
     * fallar, o nunca hubo imagen que bajar.
     */
    public function tienePortada(): bool
    {
        return $this->portada_ruta !== null;
    }

    /** La Portada a tamaño original, para la ficha del Libro. */
    public function portadaUrl(): ?string
    {
        return $this->portada_ruta === null
            ? null
            : Storage::disk()->url($this->portada_ruta);
    }

    /**
     * La versión reducida, para las retículas del Acervo y de la Vitrina. Si
     * por alguna razón no se guardó, cae en la original antes que en nada.
     */
    public function portadaMiniaturaUrl(): ?string
    {
        return $this->portada_miniatura_ruta === null
            ? $this->portadaUrl()
            : Storage::disk()->url($this->portada_miniatura_ruta);
    }

    /**
     * Si queda de dónde volver a bajar la Portada. Sin URL de origen —Captura
     * manual, o una Fuente que no trajo imagen— la única vía es subirla.
     */
    public function puedeReintentarLaPortada(): bool
    {
        return ! $this->tienePortada() && $this->portada_origen_url !== null;
    }

    /**
     * Los Libros a los que les falta la Portada.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function sinPortada(Builder $query): void
    {
        $query->whereNull('portada_ruta');
    }

    /**
     * Los Libros que salen en la Vitrina. Toda consulta que la alimente pasa
     * por aquí (ADR-003).
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function visibles(Builder $query): void
    {
        $query->where('oculto', false);
    }

    /** @param  Builder<$this>  $query */
    #[Scope]
    protected function enAcervo(Builder $query): void
    {
        $query->where('situacion', Situacion::Acervo);
    }

    /** @param  Builder<$this>  $query */
    #[Scope]
    protected function porComprar(Builder $query): void
    {
        $query->where('situacion', Situacion::PorComprar);
    }

    /** @param  Builder<$this>  $query */
    #[Scope]
    protected function leidos(Builder $query): void
    {
        $query->where('leido', true);
    }
}
