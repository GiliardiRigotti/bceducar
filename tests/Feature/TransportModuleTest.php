<?php

namespace Tests\Feature;

use App\Http\Controllers\LegacyController;
use App\Models\BcDemoEntity;
use App\Models\LegacyUser;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TransportModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_transport_package_is_registered_with_tables_and_menu(): void
    {
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        $this->assertTrue(Schema::hasTable('modules.veiculo'));
        $this->assertTrue(Schema::hasTable('modules.rota_transporte_escolar'));
        $this->assertSame(11, DB::table('modules.tipo_veiculo')->count());
        $this->assertDatabaseHas('menus', ['old' => 69, 'title' => 'Transporte escolar', 'active' => true]);
        $this->assertFileExists(LegacyController::resolve('intranet/transporte_empresa_lst.php', 'missing'));
        $this->assertFileExists(LegacyController::resolve('Api/Views/VeiculoController', 'missing'));
        $formRoot = LegacyController::resolve('TransporteEscolar/Views/VeiculoController', 'missing');
        $this->assertFileExists($formRoot . '/TransporteEscolar/Views/VeiculoController.php');
    }

    public function test_authorized_operator_can_open_transport_page(): void
    {
        if (!BcDemoEntity::query()->where('key', 'complete')->exists()) {
            $this->seed(BalnearioCamboriuDemoSeeder::class);
        }
        $actor = LegacyUser::query()->where('ref_cod_tipo_usuario', 1)->where('ativo', 1)->firstOrFail();
        $this->actingAs($actor);
        $this->get('/intranet/educar_transporte_escolar_index.php')->assertOk();
        foreach (['empresa', 'motorista', 'ponto', 'rota', 'veiculo', 'pessoa'] as $resource) {
            $response = $this->get('/intranet/transporte_' . $resource . '_lst.php');
            $this->assertSame(200, $response->status(), $resource . ': ' . $response->headers->get('Location'));
        }
    }

    public function test_global_administrator_can_open_vehicle_form(): void
    {
        $actor = LegacyUser::query()->where('ref_cod_tipo_usuario', 1)->where('ativo', 1)->firstOrFail();
        $this->actingAs($actor);
        $this->get('/module/TransporteEscolar/Veiculo')->assertOk();
    }
}
