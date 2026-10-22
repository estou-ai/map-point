<?php

namespace Estouai\MapPoint\Tests\Geocoders;

use Estouai\MapPoint\Geocoders\GooglePlaces;
use Estouai\MapPoint\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class GooglePlacesTest extends TestCase
{
    public function test_search_maps_predictions_to_rows()
    {
        Http::fake(['*' => Http::response(['status' => 'OK', 'predictions' => [
            ['place_id' => 'abc', 'structured_formatting' => ['main_text' => 'Farmácia X', 'secondary_text' => 'Lisboa']],
        ]])]);

        $rows = (new GooglePlaces('test-key'))->search('farmacia');

        $this->assertSame('Farmácia X', $rows[0]['name']);
        $this->assertSame('Lisboa', $rows[0]['formatted_address']);
        $this->assertSame('abc', $rows[0]['place_id']);
        $this->assertNull($rows[0]['lat']);
    }

    #[DataProvider('googleErrorStatuses')]
    public function test_search_throws_on_a_google_error_status(string $status)
    {
        Http::fake(['*' => Http::response(['status' => $status])]);

        $this->expectException(RuntimeException::class);

        (new GooglePlaces('test-key'))->search('farmacia');
    }

    public static function googleErrorStatuses(): array
    {
        return [['REQUEST_DENIED'], ['OVER_QUERY_LIMIT'], ['INVALID_REQUEST'], ['UNKNOWN_ERROR']];
    }

    public function test_search_throws_for_zero_results_so_the_controller_can_fall_back()
    {
        Http::fake(['*' => Http::response(['status' => 'ZERO_RESULTS', 'predictions' => []])]);

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
        Http::fake(['*' => Http::response(['status' => 'OK', 'result' => [
            'name' => 'Farmácia X',
            'formatted_address' => 'Rua X, Lisboa',
            'geometry' => ['location' => ['lat' => 38.7223, 'lng' => -9.1393]],
        ]])]);

        $row = (new GooglePlaces('test-key'))->details('abc');

        $this->assertSame(38.7223, $row['lat']);
        $this->assertSame(-9.1393, $row['lng']);
        $this->assertSame('abc', $row['place_id']);
        $this->assertSame('Rua X, Lisboa', $row['formatted_address']);
    }

    public function test_details_throws_on_an_error_status()
    {
        Http::fake(['*' => Http::response(['status' => 'REQUEST_DENIED'])]);

        $this->expectException(RuntimeException::class);

        (new GooglePlaces('test-key'))->details('abc');
    }
}
