<?php

namespace Tests\Feature\Dominio;

use App\Models\Categoria;
use App\Models\Libro;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_categoria_tiene_varios_libros(): void
    {
        $categoria = Categoria::factory()->llamada('Ciencia ficción')->create();
        $libros = Libro::factory()->count(3)->create();

        $categoria->libros()->attach($libros);

        $this->assertCount(3, $categoria->fresh()->libros);
    }

    public function test_el_nombre_no_se_repite(): void
    {
        Categoria::factory()->llamada('Ensayo')->create();

        $this->expectException(QueryException::class);

        Categoria::factory()->llamada('Ensayo')->create();
    }

    public function test_el_slug_sale_del_nombre_y_sirve_para_la_url(): void
    {
        $categoria = Categoria::factory()->llamada('Ciencia ficción')->create();

        $this->assertSame('ciencia-ficcion', $categoria->slug);
        $this->assertSame('slug', $categoria->getRouteKeyName());
    }

    public function test_al_borrar_una_categoria_los_libros_se_quedan(): void
    {
        $categoria = Categoria::factory()->create();
        $libro = Libro::factory()->create();
        $categoria->libros()->attach($libro);

        $categoria->delete();

        $this->assertSame(1, Libro::count());
        $this->assertCount(0, $libro->fresh()->categorias);
    }
}
