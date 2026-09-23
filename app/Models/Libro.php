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
