<?php

namespace App\Console\Commands;

use App\Models\BcDemoEntity;
use App\Services\CacheService;
use App\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConfigureBcPmd extends Command
{
    protected $signature = 'bc:pmd-configure';

    protected $description = 'Configura o PMD local para Balneário Camboriú preservando token e credenciais existentes';

    public function handle(): int
    {
        if (!app()->environment(['local', 'testing', 'staging', 'homologation'])) {
            $this->error('Este comando de configuração de homologação não pode executar em produção.');

            return self::FAILURE;
        }
        DB::transaction(function () {
            foreach (['prematricula.city' => 'Balneário Camboriú', 'prematricula.state' => 'SC',
                'prematricula.ibge_codes' => '4202008', 'prematricula.map.lat' => '-26.99', 'prematricula.map.lng' => '-48.63',
                'prematricula.link_to_restrict_area' => '/matricula-digital'] as $key => $value) {
                $setting = Setting::query()->where('key', $key)->firstOrFail();
                $setting->update(['value' => $value]);
            }
            // Only the explicitly identified local demo process is reconfigured.
            $processId = BcDemoEntity::query()->where('key', 'pmd.process')->value('legacy_id');
            if ($processId) {
                DB::table('processes')->where('id', $processId)->update(['waiting_list_limit' => 2, 'show_waiting_list' => true]);
                DB::table('process_stages')->where('process_id', $processId)->update(['allow_waiting_list' => true]);
            }
            $token = Setting::query()->firstOrCreate(['key' => 'prematricula.token'], ['value' => Str::random(64), 'type' => 'string', 'description' => 'Token PMD']);
            if (!$token->value) {
                $token->update(['value' => Str::random(64)]);
            }
        });
        CacheService::clearSettings();
        $this->info('PMD configurado para Balneário Camboriú/SC. Coordenadas de demonstração; credenciais externas preservadas.');

        return self::SUCCESS;
    }
}
