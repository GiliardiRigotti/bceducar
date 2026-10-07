<?php

namespace Tests\Feature;

use App\Models\BcDemoEntity;
use App\Models\RegistrationRequest;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GuardianDemoAccessTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        if (!BcDemoEntity::where('key', 'complete')->exists()) {
            $this->seed(BalnearioCamboriuDemoSeeder::class);
        }
    }

    public function test_expired_examples_still_open_profile_without_changing_deadlines(): void
    {
        $this->app['env'] = 'local';
        $deadline = now()->subDays(2)->startOfDay();
        RegistrationRequest::where('seed_source', BalnearioCamboriuDemoSeeder::SOURCE)
            ->update(['document_deadline' => $deadline]);
        $before = RegistrationRequest::where('seed_source', BalnearioCamboriuDemoSeeder::SOURCE)
            ->pluck('document_deadline', 'id')->map(fn ($date) => $date->toDateTimeString())->all();
        $this->withSession(['bc_guardian_challenge' => 'old-challenge'])
            ->get(route('bc-guardian.demo'))->assertRedirect(route('bc-guardian.show'))
            ->assertSessionHas('bc_guardian_profile_id')->assertSessionHas('bc_guardian_pmd_id')
            ->assertSessionMissing('bc_guardian_challenge');
        $selected = RegistrationRequest::where('pmd_preregistration_id', session('bc_guardian_pmd_id'))->firstOrFail();
        $this->assertSame(BalnearioCamboriuDemoSeeder::SOURCE, $selected->seed_source);
        $this->get(route('bc-guardian.show'))->assertOk()->assertSee('Meu perfil e dependentes');
        $this->get(route('bc-guardian.profile'))->assertOk()->assertSee('Meus dependentes');
        $after = RegistrationRequest::where('seed_source', BalnearioCamboriuDemoSeeder::SOURCE)
            ->pluck('document_deadline', 'id')->map(fn ($date) => $date->toDateTimeString())->all();
        $this->assertSame($before, $after);
    }

    public function test_demo_access_remains_unavailable_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->get(route('bc-guardian.demo'))->assertNotFound()->assertSessionMissing('bc_guardian_profile_id');
    }

    public function test_missing_demo_data_returns_guidance_instead_of_not_found(): void
    {
        $this->app['env'] = 'local';
        RegistrationRequest::where('seed_source', BalnearioCamboriuDemoSeeder::SOURCE)
            ->update(['seed_source' => 'UNAVAILABLE_DEMO_TEST']);
        $this->get(route('bc-guardian.demo'))->assertRedirect(route('bc-guardian.show'))->assertSessionHasErrors('demo');
    }
}
