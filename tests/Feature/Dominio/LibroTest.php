<?php

namespace Tests\Feature\Dominio;

use App\Enums\EstadoLectura;
use App\Enums\Procedencia;
use App\Enums\Situacion;
use App\Models\Categoria;
use App\Models\Cita;
use App\Models\Libro;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibroTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_mismo_isbn_no_se_registra_dos_veces(): void
    {
        Libro::factory()->create(['isbn' => '9786073100000']);

        $this->expectException(QueryException::class);

        Libro::factory()->create(['isbn' => '9786073100000']);
    }

    public function test_un_libro_sin_isbn_se_puede_registrar_mas_de_una_vez(): void
    {
        Libro::factory()->sinIsbn()->create();
        Libro::factory()->sinIsbn()->create();

        $this->assertSame(2, Libro::whereNull('isbn')->count());
    }

    public function test_un_libro_nace_visible(): void
    {
        $libro = Libro::create([
            'titulo' => 'Pedro Páramo',
            'autores' => ['Juan Rulfo'],
        ]);

        $this->assertFalse($libro->fresh()->oculto);
    }

    public function test_un_libro_nace_en_el_acervo(): void
    {
        $libro = Libro::create([
            'titulo' => 'Pedro Páramo',
            'autores' => ['Juan Rulfo'],
        ]);

        $this->assertSame(Situacion::Acervo, $libro->fresh()->situacion);
    }

    public function test_la_lectura_distingue_tres_estados(): void
    {
        $noLeido = Libro::factory()->create();
        $sinFecha = Libro::factory()->leidoSinFecha()->create();
        $conFecha = Libro::factory()->leido()->create(['fecha_lectura' => '2024-03-01']);

        $this->assertSame(EstadoLectura::NoLeido, $noLeido->fresh()->estadoLectura());
        $this->assertSame(EstadoLectura::LeidoSinFecha, $sinFecha->fresh()->estadoLectura());
        $this->assertSame(EstadoLectura::LeidoConFecha, $conFecha->fresh()->estadoLectura());

        $this->assertFalse($noLeido->estadoLectura()->estaLeido());
        $this->assertTrue($sinFecha->estadoLectura()->estaLeido());
        $this->assertTrue($conFecha->estadoLectura()->estaLeido());
    }

    public function test_leido_sin_fecha_no_se_confunde_con_no_leido_en_las_consultas(): void
    {
        Libro::factory()->create();
        Libro::factory()->leidoSinFecha()->create();
        Libro::factory()->leido()->create();

        $this->assertSame(2, Libro::query()->leidos()->count());
        $this->assertSame(1, Libro::query()->leidos()->whereNull('fecha_lectura')->count());
    }

    public function test_la_descripcion_guarda_de_cual_de_las_tres_procedencias_viene(): void
    {
        $deFuente = Libro::factory()->descripcionDeFuente()->create();
        $deIa = Libro::factory()->descripcionDeIa()->create();
        $mia = Libro::factory()->descripcionMia()->create();
        $sinDescripcion = Libro::factory()->sinDescripcion()->create();

        $this->assertSame(Procedencia::Fuente, $deFuente->fresh()->descripcion_procedencia);
        $this->assertSame(Procedencia::Ia, $deIa->fresh()->descripcion_procedencia);
        $this->assertSame(Procedencia::Mia, $mia->fresh()->descripcion_procedencia);
        $this->assertNull($sinDescripcion->fresh()->descripcion_procedencia);
    }

    public function test_solo_la_descripcion_de_ia_se_regenera(): void
    {
        $this->assertTrue(Procedencia::Ia->seRegenera());
        $this->assertFalse(Procedencia::Fuente->seRegenera());
        $this->assertFalse(Procedencia::Mia->seRegenera());
    }

    public function test_guarda_con_que_modelo_se_genero_lo_suyo(): void
    {
        $libro = Libro::factory()
            ->descripcionDeIa('gemini', 'gemini-2.5-flash')
            ->categoriasGeneradas('gemini', 'gemini-2.5-flash')
            ->create()
            ->fresh();

        $this->assertSame('gemini', $libro->descripcion_proveedor);
        $this->assertSame('gemini-2.5-flash', $libro->descripcion_modelo);
        $this->assertSame('gemini', $libro->categorias_proveedor);
        $this->assertSame('gemini-2.5-flash', $libro->categorias_modelo);
    }

    public function test_un_libro_al_que_nadie_le_genero_categorias_no_tiene_modelo(): void
    {
        $libro = Libro::factory()->create()->fresh();

        $this->assertNull($libro->categorias_modelo);
        $this->assertCount(0, $libro->categorias);
    }

    public function test_los_autores_conservan_su_orden(): void
    {
        $libro = Libro::factory()->create([
            'autores' => ['Jorge Luis Borges', 'Adolfo Bioy Casares'],
        ]);

        $this->assertSame(['Jorge Luis Borges', 'Adolfo Bioy Casares'], $libro->fresh()->autores);
    }

    public function test_un_libro_tiene_varias_citas(): void
    {
        $libro = Libro::factory()->create();

        Cita::factory()->count(3)->create(['libro_id' => $libro->id]);

        $this->assertCount(3, $libro->fresh()->citas);
        $this->assertTrue($libro->citas->first()->libro->is($libro));
    }

    public function test_las_citas_se_van_con_el_libro(): void
    {
        $libro = Libro::factory()->has(Cita::factory()->count(2))->create();

        $libro->delete();

        $this->assertSame(0, Cita::count());
    }

    public function test_un_libro_tiene_varias_categorias(): void
    {
        $libro = Libro::factory()->create();
        $categorias = Categoria::factory()->count(2)->create();

        $libro->categorias()->attach($categorias);

        $this->assertCount(2, $libro->fresh()->categorias);
    }

    public function test_la_misma_categoria_no_se_repite_en_un_libro(): void
    {
        $libro = Libro::factory()->create();
        $categoria = Categoria::factory()->create();

        $libro->categorias()->syncWithoutDetaching($categoria);
        $libro->categorias()->syncWithoutDetaching($categoria);

        $this->assertCount(1, $libro->fresh()->categorias);
    }

    public function test_los_libros_ocultos_quedan_fuera_de_las_consultas_de_la_vitrina(): void
    {
        $visible = Libro::factory()->create();
        Libro::factory()->oculto()->create();

        $visibles = Libro::query()->visibles()->get();

        $this->assertCount(1, $visibles);
        $this->assertTrue($visibles->first()->is($visible));
    }

    public function test_acervo_y_por_comprar_son_la_misma_entidad_con_distinta_situacion(): void
    {
        $porComprar = Libro::factory()->porComprar()->create();
        Libro::factory()->create();

        $this->assertSame(1, Libro::query()->porComprar()->count());
        $this->assertSame(1, Libro::query()->enAcervo()->count());

        $porComprar->update(['situacion' => Situacion::Acervo]);

        $this->assertSame(0, Libro::query()->porComprar()->count());
        $this->assertSame(2, Libro::query()->enAcervo()->count());
    }

    public function test_el_prestamo_es_un_par_de_campos_y_se_borra_cuando_vuelve(): void
    {
        $libro = Libro::factory()->prestado('Ana')->create();

        $this->assertSame('Ana', $libro->fresh()->prestado_a);
        $this->assertNotNull($libro->fresh()->prestado_el);

        $libro->update(['prestado_a' => null, 'prestado_el' => null]);

        $this->assertNull($libro->fresh()->prestado_a);
        $this->assertNull($libro->fresh()->prestado_el);
    }
}
