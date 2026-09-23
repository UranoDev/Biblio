<?php

namespace Tests\Feature\Dominio;

use App\Models\ConfiguracionModelo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConfiguracionModeloTest extends TestCase
{
    use RefreshDatabase;

    public function test_al_principio_no_hay_modelo_elegido(): void
    {
        $this->assertNull(ConfiguracionModelo::elegido());
    }

    public function test_guarda_el_proveedor_y_el_modelo_elegidos(): void
    {
        ConfiguracionModelo::elegir('gemini', 'gemini-2.5-flash');

        $elegido = ConfiguracionModelo::elegido();

        $this->assertNotNull($elegido);
        $this->assertSame('gemini', $elegido->proveedor);
        $this->assertSame('gemini-2.5-flash', $elegido->modelo);
    }

    public function test_elegir_otro_modelo_reemplaza_al_anterior(): void
    {
        ConfiguracionModelo::elegir('gemini', 'gemini-2.5-flash');
        ConfiguracionModelo::elegir('deepseek', 'deepseek-chat');

        $elegido = ConfiguracionModelo::elegido();

        $this->assertSame(1, ConfiguracionModelo::count());
        $this->assertNotNull($elegido);
        $this->assertSame('deepseek', $elegido->proveedor);
        $this->assertSame('deepseek-chat', $elegido->modelo);
    }

    public function test_ninguna_llave_de_api_se_guarda_en_la_base(): void
    {
        $tablas = ['configuracion_modelo', 'libros', 'citas', 'categorias'];

        foreach ($tablas as $tabla) {
            foreach (Schema::getColumnListing($tabla) as $columna) {
                $this->assertFalse(
                    Str::contains($columna, ['llave', 'key', 'api', 'token', 'secret'], ignoreCase: true),
                    "La columna [{$tabla}.{$columna}] parece guardar una llave de API; las llaves viven en el entorno."
                );
            }
        }
    }
}
