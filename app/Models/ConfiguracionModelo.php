<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * El proveedor y el Modelo elegidos para escribir Categorías y Descripciones.
 *
 * Es una sola fila: elegir otro Modelo reemplaza la que hay. Las llaves de API
 * no se guardan aquí ni en ninguna otra tabla — viven en el entorno (ADR-006).
 *
 * @property int $id
 * @property string $proveedor
 * @property string $modelo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ConfiguracionModelo extends Model
{
    protected $table = 'configuracion_modelo';

    protected $guarded = ['id'];

    /**
     * El Modelo elegido, o null mientras nadie haya elegido uno.
     */
    public static function elegido(): ?self
    {
        return static::query()->latest('id')->first();
    }

    /**
     * Guardar el proveedor y el Modelo, reemplazando la elección anterior.
     */
    public static function elegir(string $proveedor, string $modelo): self
    {
        $configuracion = static::elegido() ?? new self;

        $configuracion->proveedor = $proveedor;
        $configuracion->modelo = $modelo;
        $configuracion->save();

        return $configuracion;
    }
}
