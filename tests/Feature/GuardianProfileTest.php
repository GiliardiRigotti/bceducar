<?php

namespace Tests\Feature;

use App\EnrollmentRequests\GuardianProfiles;
use App\Mail\GuardianAccessCode;
use App\Models\BcDemoEntity;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GuardianProfileTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        if (!BcDemoEntity::query()->where('key', 'complete')->exists()) {
            $this->seed(BalnearioCamboriuDemoSeeder::class);
        }
        Mail::fake();
    }

    private function verifyApplication(PreRegistration $pmd): void
    {
        $this->post('/matricula-digital/acesso', ['protocol' => $pmd->protocol,
            'email' => $pmd->responsible->email])->assertRedirect();
        $mail = Mail::sent(GuardianAccessCode::class)->last();
        $this->assertNotNull($mail);
        $this->post('/matricula-digital/verificar', ['code' => $mail->code])->assertRedirect();
    }

    public function test_verified_profile_recovers_only_confirmed_applications(): void
    {
        $pmd = PreRegistration::query()->with('responsible')->firstOrFail();
        $other = PreRegistration::query()->where('responsible_id', '!=', $pmd->responsible_id)->firstOrFail();
        $other->responsible->update(['email' => $pmd->responsible->email]);
        $this->verifyApplication($pmd);
        $profileId = session('bc_guardian_profile_id');
        $this->assertCount(1, app(GuardianProfiles::class)->applications($profileId));
        $this->get('/matricula-digital/perfil')->assertOk()->assertSee($pmd->protocol)->assertDontSee($other->protocol);
        $this->post('/matricula-digital/inscricoes/' . $other->id . '/selecionar')->assertForbidden();
        $this->post('/matricula-digital/sair')->assertRedirect();
        $this->get('/matricula-digital/perfil')->assertForbidden();
        $this->post('/matricula-digital/acesso', ['email' => $pmd->responsible->email])->assertRedirect();
        $code = Mail::sent(GuardianAccessCode::class)->last()->code;
        $this->post('/matricula-digital/verificar', ['code' => $code])->assertRedirect();
        $this->assertSame($profileId, session('bc_guardian_profile_id'));
        $this->assertCount(1, app(GuardianProfiles::class)->applications($profileId));
        $this->verifyApplication($other);
        $this->assertCount(2, app(GuardianProfiles::class)->applications($profileId));
        $this->post('/matricula-digital/inscricoes/' . $other->id . '/selecionar')->assertRedirect();
        $this->assertSame((int) $other->id, session('bc_guardian_pmd_id'));
    }

    public function test_unconfirmed_email_cannot_recover_or_create_a_profile(): void
    {
        $before = DB::table('bc_guardian_profiles')->count();
        $this->post('/matricula-digital/acesso', ['email' => 'unknown@invalid.test'])->assertRedirect();
        Mail::assertNothingOutgoing();
        $this->assertSame($before, DB::table('bc_guardian_profiles')->count());
        $this->post('/matricula-digital/inscricoes/1/selecionar')->assertForbidden();
    }
}
