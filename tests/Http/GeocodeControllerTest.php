<?php

namespace Estouai\MapPoint\Tests\Http;

use Estouai\MapPoint\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;

class GeocodeControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        File::deleteDirectory(__DIR__.'/../__fixtures__/users');

        parent::tearDown();
    }

    public function test_search_requires_authentication()
    {
        $this->get(cp_route('map-point.search', ['q' => 'x']))->assertRedirect();
    }

    public function test_search_returns_google_results_when_google_works()
    {
        config(['map-point.google_api_key' => 'test-key']);
        Http::fake(['*googleapis*' => Http::response(['suggestions' => [
            ['placePrediction' => [
                'placeId' => 'abc',
                'structuredFormat' => [
                    'mainText' => ['text' => 'Farmácia X'],
                    'secondaryText' => ['text' => 'Lisboa'],
                ],
            ]],
        ]])]);

        $this->actingAs($this->userWithCpAccess())
            ->get(cp_route('map-point.search', ['q' => 'farmacia', 'provider' => 'google']))
            ->assertOk()
            ->assertJsonPath('fallback', false)
            ->assertJsonPath('results.0.place_id', 'abc');
    }

    public function test_search_falls_back_to_nominatim_for_zero_results()
    {
        config(['map-point.google_api_key' => 'test-key']);
        Http::fake([
            '*googleapis*' => Http::response([]),
            '*nominatim*' => Http::response([['display_name' => 'Rua X', 'lat' => '38.7', 'lon' => '-9.1']]),
        ]);

        $this->actingAs($this->userWithCpAccess())
            ->get(cp_route('map-point.search', ['q' => 'farmacia', 'provider' => 'google']))
            ->assertOk()
            ->assertJsonPath('fallback', true)
            ->assertJsonPath('results.0.lat', 38.7);
    }

    public function test_search_falls_back_to_nominatim_when_google_fails()
    {
        config(['map-point.google_api_key' => 'test-key']);
        Http::fake([
            '*googleapis*' => Http::response(['error' => ['status' => 'PERMISSION_DENIED']], 403),
            '*nominatim*' => Http::response([['display_name' => 'Rua X', 'lat' => '38.7', 'lon' => '-9.1']]),
        ]);

        $this->actingAs($this->userWithCpAccess())
            ->get(cp_route('map-point.search', ['q' => 'farmacia', 'provider' => 'google']))
            ->assertOk()
            ->assertJsonPath('fallback', true)
            ->assertJsonPath('results.0.lat', 38.7);
    }

    public function test_search_falls_back_when_no_api_key_is_configured()
    {
        config(['map-point.google_api_key' => null]);
        Http::fake(['*nominatim*' => Http::response([['display_name' => 'Rua X', 'lat' => '38.7', 'lon' => '-9.1']])]);

        $this->actingAs($this->userWithCpAccess())
            ->get(cp_route('map-point.search', ['q' => 'farmacia', 'provider' => 'google']))
            ->assertOk()
            ->assertJsonPath('fallback', true);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'googleapis'));
    }

    public function test_nominatim_provider_never_calls_google()
    {
        config(['map-point.google_api_key' => 'test-key']);
        Http::fake(['*nominatim*' => Http::response([['display_name' => 'Rua X', 'lat' => '38.7', 'lon' => '-9.1']])]);

        $this->actingAs($this->userWithCpAccess())
            ->get(cp_route('map-point.search', ['q' => 'rua x', 'provider' => 'nominatim']))
            ->assertOk()
            ->assertJsonPath('fallback', false);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'googleapis'));
    }

    #[DataProvider('emptyQueries')]
    public function test_an_empty_query_never_reaches_a_geocoder($query)
    {
        Http::fake();

        $this->actingAs($this->userWithCpAccess())
            ->get(cp_route('map-point.search', ['q' => $query]))
            ->assertOk()
            ->assertJsonPath('results', []);

        Http::assertNothingSent();
    }

    public static function emptyQueries(): array
    {
        return ['empty' => [''], 'whitespace' => ['   ']];
    }

    public function test_an_over_long_query_is_rejected()
    {
        Http::fake();

        $this->actingAs($this->userWithCpAccess())
            ->getJson(cp_route('map-point.search', ['q' => str_repeat('a', 300)]))
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_search_fails_cleanly_when_nominatim_errors()
    {
        config(['map-point.google_api_key' => null]);
        Http::fake(['*nominatim*' => Http::response('', 429)]);

        $this->actingAs($this->userWithCpAccess())
            ->getJson(cp_route('map-point.search', ['q' => 'rua x', 'provider' => 'nominatim']))
            ->assertStatus(503);
    }

    public function test_details_returns_a_resolved_coordinate()
    {
        config(['map-point.google_api_key' => 'test-key']);
        Http::fake(['*googleapis*' => Http::response([
            'displayName' => ['text' => 'Farmácia X'],
            'formattedAddress' => 'Rua X, Lisboa',
            'location' => ['latitude' => 38.7223, 'longitude' => -9.1393],
        ])]);

        $this->actingAs($this->userWithCpAccess())
            ->get(cp_route('map-point.details', ['place_id' => 'abc']))
            ->assertOk()
            ->assertJsonPath('results.0.lat', 38.7223);
    }

    public function test_details_fails_cleanly_without_google()
    {
        config(['map-point.google_api_key' => null]);

        $this->actingAs($this->userWithCpAccess())
            ->get(cp_route('map-point.details', ['place_id' => 'abc']))
            ->assertStatus(503);
    }

    public function test_details_fails_cleanly_when_google_errors()
    {
        config(['map-point.google_api_key' => 'test-key']);
        Http::fake(['*googleapis*' => Http::response(['error' => ['status' => 'RESOURCE_EXHAUSTED']], 429)]);

        $this->actingAs($this->userWithCpAccess())
            ->get(cp_route('map-point.details', ['place_id' => 'abc']))
            ->assertStatus(503);
    }
}
