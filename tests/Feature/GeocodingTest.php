<?php

namespace Tests\Feature;

use App\Geo\GeocodingService;
use iEducar\Packages\PreMatricula\Http\Controllers\ConfigController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodingTest extends TestCase
{
    public function test_guardian_navigation_defaults_to_portal_and_preserves_custom_url(): void
    {
        $controller = new ConfigController;
        foreach ([[null, '/matricula-digital'], ['/portal-municipal', '/portal-municipal']] as [$configured, $expected]) {
            config(['prematricula.link_to_restrict_area' => $configured]);
            $javascript = $controller->config()->getContent();
            $payload = json_decode(substr($javascript, strlen('window.config = '), -1), true);
            $this->assertSame($expected, $payload['link_to_restrict_area']);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'maps.geocoding_url' => 'https://geo.municipal.test']);
        Http::preventStrayRequests();
    }

    public function test_coordinates_accept_legacy_text_and_reject_invalid_ranges(): void
    {
        $this->assertTrue(GeocodingService::validCoordinates(' -26.99 ', '-48.63'));
        $this->assertTrue(GeocodingService::validCoordinates(0, 0));
        $this->assertFalse(GeocodingService::validCoordinates(91, -48));
        $this->assertFalse(GeocodingService::validCoordinates(-26, -181));
        $this->assertFalse(GeocodingService::validCoordinates('invalid', null));
    }

    public function test_search_normalizes_and_caches_provider_response(): void
    {
        Http::fake(['geo.municipal.test/*' => Http::response([['lat' => '-26.99', 'lon' => '-48.63', 'display_name' => 'Endere?o']])]);
        $service = app(GeocodingService::class);
        $first = $service->geocode('Endere?o de teste');
        $this->assertSame(-26.99, $first['latitude']);
        $this->assertSame(-48.63, $first['longitude']);
        $this->assertSame($first, $service->geocode('Endere?o de teste'));
        Http::assertSentCount(1);
    }

    public function test_public_provider_cannot_receive_residential_addresses(): void
    {
        config(['maps.geocoding_url' => 'https://nominatim.openstreetmap.org']);
        $this->postJson('/geo/search', ['address' => 'Endereco'])->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_public_host_variants_and_invalid_provider_urls_are_blocked(): void
    {
        foreach ([
            'https://NOMINATIM.OPENSTREETMAP.ORG./',
            'https://api.nominatim.openstreetmap.org',
            'ftp://geo.municipal.test',
            'https://user:secret@geo.municipal.test',
            'https://geo.municipal.test?token=secret',
            '',
        ] as $url) {
            Cache::flush();
            config(['maps.geocoding_url' => $url]);
            $this->postJson('/geo/search', ['address' => 'Endereco'])->assertStatus(503);
        }
        Http::assertNothingSent();
    }

    public function test_provider_redirect_cannot_forward_residential_address(): void
    {
        Http::fake(function ($request, $options) {
            $this->assertFalse($options['allow_redirects']);

            return Http::response('', 302, [
                'Location' => 'https://nominatim.openstreetmap.org/search',
            ]);
        });
        $this->postJson('/geo/search', ['address' => 'Endereco'])->assertStatus(503);
        Http::assertSentCount(1);
    }

    public function test_unavailable_invalid_and_missing_results_have_safe_responses(): void
    {
        foreach ([Http::response([], 200), Http::response([['lat' => 'invalid', 'lon' => 0]], 200), Http::response([], 500)] as $index => $response) {
            Cache::flush();
            config(['maps.geocoding_url' => 'https://geo'.$index.'.municipal.test']);
            Http::fake(['geo'.$index.'.municipal.test/*' => $response]);
            $this->postJson('/geo/search', ['address' => 'Endereco'])->assertStatus($index === 0 ? 404 : 503);
        }
    }

    public function test_timeout_and_global_rate_limit_do_not_expose_provider_errors(): void
    {
        Http::fake(['geo.municipal.test/*' => Http::failedConnection()]);
        $this->postJson('/geo/search', ['address' => 'Endereco'])->assertStatus(503)->assertJsonMissing(['exception']);
        Http::fake(['geo.municipal.test/*' => Http::response([['lat' => -26, 'lon' => -48]])]);
        $this->postJson('/geo/search', ['address' => 'OutroEndereco'])->assertStatus(503);
    }

    public function test_config_exposes_tiles_without_private_provider_address(): void
    {
        $this->getJson('/geo/config')->assertOk()->assertJsonStructure(['tileUrl', 'attribution', 'maxZoom', 'defaultLatitude', 'defaultLongitude', 'defaultZoom'])->assertJsonMissing(['geocoding_url']);
    }
}
