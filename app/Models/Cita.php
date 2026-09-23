<?php

namespace App\Models;

use Database\Factories\CitaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un fragmento textual del Libro, con su página si se anotó. Son las palabras
 * del Libro, no mías — eso es el Apunte, que vive en el Libro.
 *
 * @property int $id
 * @property int $libro_id
 * @property string $texto
 * @property int|null $pagina
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Libro $libro
 */
class Cita extends Model
{
    /** @use HasFactory<CitaFactory> */
    use HasFactory;

    protected $table = 'citas';

    protected $guarded = ['id'];

    /** @return BelongsTo<Libro, $this> */
    public function libro(): BelongsTo
    {
        return $this->belongsTo(Libro::class);
    }
}
