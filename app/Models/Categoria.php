<?php

namespace App\Models;

use Database\Factories\CategoriaFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * El tema al que pertenece un Libro. En la Vitrina es el modo de navegar, y de
 * ahí el slug.
 *
 * @property int $id
 * @property string $nombre
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Libro> $libros
 */
class Categoria extends Model
{
    /** @use HasFactory<CategoriaFactory> */
    use HasFactory;

    protected $table = 'categorias';

    protected $guarded = ['id'];

    /** @return BelongsToMany<Libro, $this> */
    public function libros(): BelongsToMany
    {
        return $this->belongsToMany(Libro::class, 'categoria_libro');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
