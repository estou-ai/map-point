<?php

namespace Estouai\MapPoint\Tests\Geocoders;

use Estouai\MapPoint\Geocoders\Nominatim;
use Estouai\MapPoint\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NominatimTest extends TestCase
{
    public function test_search_maps_results_to_rows()
    {
        Http::fake(['*' => Http::response([
            ['display_name' => 'Rua X, Lisboa', 'lat' => '38.7223', 'lon' => '-9.1393'],
        ])]);

        $rows = (new Nominatim)->search('rua x');

        $this->assertSame(38.7223, $rows[0]['lat']);
        $this->assertSame(-9.1393, $rows[0]['lng']);
        $this->assertSame('Rua X, Lisboa', $rows[0]['formatted_address']);
        $this->assertNull($rows[0]['place_id']);
    }

    public function test_search_sends_a_descriptive_user_agent()
    {
        Http::fake(['*' => Http::response([])]);

        (new Nominatim)->search('rua x');

        Http::assertSent(fn ($request) => str_contains($request->header('User-Agent')[0] ?? '', 'map-point'));
    }

    public function test_search_throws_on_a_transport_failure()
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->expectException(RuntimeException::class);

        (new Nominatim)->search('rua x');
    }
}
