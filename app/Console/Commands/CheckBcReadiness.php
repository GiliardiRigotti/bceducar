<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckBcReadiness extends Command
{
    protected $signature = 'bc:check-readiness';

    protected $description = 'Verifica configuração operacional sem enviar mensagens, consultar provedores ou alterar dados';

    public function handle(): int
    {
        $checks = [
            'HTTPS da aplicação' => str_starts_with((string) config('app.url'), 'https://'),
            'SMTP configurado com TLS' => config('mail.default') === 'smtp'
                && filled(config('mail.mailers.smtp.host'))
                && !in_array(strtolower((string) config('mail.mailers.smtp.host')), ['mailpit', 'localhost', '127.0.0.1'])
                && in_array(config('mail.mailers.smtp.encryption'), ['tls', 'ssl'], true)
                && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL),
            'Tiles próprios configurados com HTTPS' => $this->endpoint(config('maps.tile_url'))
                && strtolower((string) parse_url(config('maps.tile_url'), PHP_URL_HOST)) !== 'tile.openstreetmap.org',
            'Política de retenção referenciada' => config('bc-retention.approved')
                && filled(config('bc-retention.reference'))
                && filter_var(config('bc-retention.days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 36500]]) !== false,
        ];
        $rows = [];
        foreach ($checks as $label => $configured) {
            $rows[] = [$label, $configured ? 'CONFIGURADO' : 'PENDENTE'];
        }
        $geocoder = config('maps.geocoding_url');
        $rows[] = ['Geocoder automático opcional', blank($geocoder) ? 'DESATIVADO'
            : ($this->endpoint($geocoder, true) ? 'CONFIGURADO' : 'CONFIGURAÇÃO INCOMPLETA')];
        foreach (['sms', 'whatsapp'] as $channel) {
            $enabled = config('bc-messages.enabled') && config('bc-messages.channels.'.$channel.'.enabled');
            $ready = $this->endpoint(config('bc-messages.channels.'.$channel.'.url'))
                && $this->validCutoff(config('bc-messages.start_at'));
            $rows[] = [strtoupper($channel), !$enabled ? 'DESATIVADO' : ($ready ? 'CONFIGURADO' : 'CONFIGURAÇÃO INCOMPLETA')];
        }
        $this->table(['Item', 'Situação'], $rows);
        $this->line('Diagnóstico de configuração: não comprova entrega externa, capacidade, homologação ou aceite.');
        $this->line('Nenhuma conexão externa, alteração de dados ou exclusão foi realizada.');

        return in_array(false, array_map(fn ($value) => (bool) $value, $checks), true)
            || collect($rows)->contains(fn ($row) => $row[1] === 'CONFIGURAÇÃO INCOMPLETA')
            ? self::FAILURE : self::SUCCESS;
    }

    private function endpoint(?string $url, bool $geocoder = false): bool
    {
        $parts = parse_url((string) $url);
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));

        return ($parts['scheme'] ?? '') === 'https' && $host !== ''
            && !isset($parts['user']) && !isset($parts['pass'])
            && !isset($parts['query']) && !isset($parts['fragment'])
            && (!$geocoder || ($host !== 'nominatim.openstreetmap.org' && !str_ends_with($host, '.nominatim.openstreetmap.org')));
    }

    private function validCutoff(?string $value): bool
    {
        return filled($value) && strtotime($value) !== false;
    }
}
