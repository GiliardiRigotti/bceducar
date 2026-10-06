<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransportCoordinatesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_point_creation_and_description_edit_preserve_coordinates(): void
    {
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        $point = new \clsModulesPontoTransporteEscolar;
        $point->descricao = 'Ponto de teste Leaflet';
        $point->latitude = '-26.991234';
        $point->longitude = '-48.634567';
        $id = $point->cadastra();
        $this->assertNotFalse($id);
        $edit = new \clsModulesPontoTransporteEscolar;
        $edit->cod_ponto_transporte_escolar = (string) $id;
        $edit->descricao = 'Descricao atualizada';
        $this->assertTrue($edit->edita());
        $row = $edit->detalhe();
        $this->assertSame(-26.991234, (float) $row['latitude']);
        $this->assertSame(-48.634567, (float) $row['longitude']);
        $route = DB::table('modules.rota_transporte_escolar')->insertGetId([
            'ref_idpes_destino' => DB::table('cadastro.juridica')->value('idpes'),
            'descricao' => 'Rota de teste Leaflet', 'ano' => 2026,
            'tipo_rota' => 'U', 'tercerizado' => 'N',
        ], 'cod_rota_transporte_escolar');
        $itinerary = DB::table('modules.itinerario_transporte_escolar')->insertGetId([
            'ref_cod_rota_transporte_escolar' => $route, 'seq' => 2,
            'ref_cod_ponto_transporte_escolar' => $id, 'tipo' => 'I',
        ], 'cod_itinerario_transporte_escolar');
        $this->assertDatabaseHas('modules.itinerario_transporte_escolar', [
            'cod_itinerario_transporte_escolar' => $itinerary,
            'ref_cod_ponto_transporte_escolar' => $id, 'seq' => 2,
        ]);
    }

    public function test_legacy_loader_no_longer_requires_google_and_supports_manual_fields(): void
    {
        $loader = file_get_contents(base_path('ieducar/lib/Utils/gmaps.js'));
        $script = file_get_contents(public_path('vendor/legacy/Portabilis/Assets/Javascripts/Frontend/ieducar.singleton_gmap.js'));
        $this->assertStringNotContainsString('google.maps', $loader.$script);
        $this->assertStringContainsString('/vendor/maps/leaflet.js', $loader);
        $this->assertStringContainsString("prop('readonly', false)", $script);
        $this->assertStringContainsString('IeducarSingletonMap.prototype.reload', $script);
    }
}
