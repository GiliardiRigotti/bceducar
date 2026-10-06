<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BcReadinessTest extends TestCase
{
    public function test_pending_configuration_is_reported_without_external_calls_or_secrets(): void
    {
        Http::preventStrayRequests();
        config(['app.url' => 'http://localhost', 'mail.default' => 'array',
            'maps.geocoding_url' => '', 'bc-retention.approved' => false,
            'bc-messages.enabled' => true, 'bc-messages.channels.sms.enabled' => true,
            'bc-messages.channels.sms.url' => 'https://bridge.test',
            'bc-messages.channels.sms.token' => 'sensitive-token', 'bc-messages.start_at' => null]);
        $this->artisan('bc:check-readiness')
            ->expectsOutputToContain('PENDENTE')
            ->expectsOutputToContain('CONFIGURAÇÃO INCOMPLETA')
            ->doesntExpectOutputToContain('sensitive-token')
            ->assertExitCode(1);
        Http::assertNothingSent();
    }

    public function test_complete_configuration_is_not_reported_as_homologated(): void
    {
        Http::preventStrayRequests();
        config(['app.url' => 'https://school.test', 'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.school.test', 'mail.mailers.smtp.encryption' => 'tls',
            'mail.from.address' => 'school@example.test', 'maps.geocoding_url' => 'https://geo.school.test',
            'maps.tile_url' => 'https://tiles.school.test/{z}/{x}/{y}.png',
            'bc-retention.approved' => true, 'bc-retention.reference' => 'test-policy',
            'bc-retention.days' => 180, 'bc-messages.enabled' => false]);
        $this->artisan('bc:check-readiness')
            ->expectsOutputToContain('CONFIGURADO')
            ->expectsOutputToContain('não comprova entrega externa')
            ->assertExitCode(0);
        config(['maps.geocoding_url' => '']);
        $this->artisan('bc:check-readiness')->expectsOutputToContain('DESATIVADO')->assertExitCode(0);
        config(['maps.geocoding_url' => 'https://nominatim.openstreetmap.org']);
        $this->artisan('bc:check-readiness')->expectsOutputToContain('CONFIGURAÇÃO INCOMPLETA')->assertExitCode(1);
        Http::assertNothingSent();
    }
}
