<?php

namespace Estouai\MapPoint\Tests\Geocoders;

use Estouai\MapPoint\Geocoders\GooglePlaces;
use Estouai\MapPoint\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GooglePlacesTest extends TestCase
{
    public function test_search_maps_suggestions_to_rows()
    {
        Http::fake(['*' => Http::response(['suggestions' => [
            ['placePrediction' => [
                'placeId' => 'abc',
                'text' => ['text' => 'Farmácia X, Lisboa'],
                'structuredFormat' => [
                    'mainText' => ['text' => 'Farmácia X'],
                    'secondaryText' => ['text' => 'Lisboa'],
                ],
            ]],
        ]])]);

        $rows = (new GooglePlaces('test-key'))->search('farmacia');

        $this->assertSame('Farmácia X', $rows[0]['name']);
        $this->assertSame('Lisboa', $rows[0]['formatted_address']);
        $this->assertSame('abc', $rows[0]['place_id']);
        $this->assertNull($rows[0]['lat']);
    }

    public function test_search_uses_the_new_places_endpoint_with_a_header_key()
    {
        Http::fake(['*' => Http::response(['suggestions' => [
            ['placePrediction' => ['placeId' => 'abc', 'text' => ['text' => 'X']]],
        ]])]);

        (new GooglePlaces('test-key'))->search('farmacia');

        Http::assertSent(fn ($request) => $request->url() === 'https://places.googleapis.com/v1/places:autocomplete'
            && $request->method() === 'POST'
            && $request->hasHeader('X-Goog-Api-Key', 'test-key')
            && $request->hasHeader('X-Goog-FieldMask')
            && ! str_contains($request->url(), 'test-key'));
    }

    public function test_search_throws_on_an_api_error()
    {
        Http::fake(['*' => Http::response(['error' => ['status' => 'PERMISSION_DENIED']], 403)]);

        $this->expectException(RuntimeException::class);

        (new GooglePlaces('test-key'))->search('farmacia');
    }

    public function test_search_throws_for_zero_results_so_the_controller_can_fall_back()
    {
        Http::fake(['*' => Http::response([])]);

        $this->expectException(RuntimeException::class);

        (new GooglePlaces('test-key'))->search('qwertyuiop');
    }

    public function test_search_throws_on_a_transport_failure()
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->expectException(RuntimeException::class);

        (new GooglePlaces('test-key'))->search('farmacia');
    }

    public function test_transport_exception_does_not_expose_the_api_key()
    {
        Http::fake(fn () => throw new RuntimeException('Failed URL https://google.test/?key=super-secret-key'));

        try {
            (new GooglePlaces('super-secret-key'))->search('farmacia');
            $this->fail('Expected transport failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString('super-secret-key', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }

    public function test_details_resolves_coordinates()
    {
        Http::fake(['*' => Http::response([
            'displayName' => ['text' => 'Farmácia X'],
            'formattedAddress' => 'Rua X, Lisboa',
            'location' => ['latitude' => 38.7223, 'longitude' => -9.1393],
        ])]);

        $row = (new GooglePlaces('test-key'))->details('abc');

        $this->assertSame(38.7223, $row['lat']);
        $this->assertSame(-9.1393, $row['lng']);
        $this->assertSame('abc', $row['place_id']);
        $this->assertSame('Rua X, Lisboa', $row['formatted_address']);
    }

    public function test_details_returns_null_without_a_location()
    {
        Http::fake(['*' => Http::response(['displayName' => ['text' => 'Farmácia X']])]);

        $this->assertNull((new GooglePlaces('test-key'))->details('abc'));
    }

    public function test_details_throws_on_an_api_error()
    {
        Http::fake(['*' => Http::response(['error' => ['status' => 'PERMISSION_DENIED']], 403)]);

        $this->expectException(RuntimeException::class);

        (new GooglePlaces('test-key'))->details('abc');
    }
}
