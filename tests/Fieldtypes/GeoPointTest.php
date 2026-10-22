<?php

namespace Estouai\MapPoint\Tests\Fieldtypes;

use Estouai\MapPoint\Fieldtypes\GeoPoint;
use Estouai\MapPoint\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Statamic\Fields\FieldtypeRepository;

class GeoPointTest extends TestCase
{
    public function test_it_is_registered_under_the_geo_point_handle()
    {
        $this->assertInstanceOf(GeoPoint::class, app(FieldtypeRepository::class)->find('geo_point'));
    }

    public function test_process_preserves_precision()
    {
        $this->assertSame(
            ['lat' => 38.7223, 'lng' => -9.1393],
            collect((new GeoPoint)->process(['lat' => 38.7223, 'lng' => -9.1393]))->only(['lat', 'lng'])->all()
        );
    }

    public function test_process_casts_numeric_strings_to_float()
    {
        $result = (new GeoPoint)->process(['lat' => '38.7223', 'lng' => '-9.1393']);

        $this->assertSame(38.7223, $result['lat']);
        $this->assertSame(-9.1393, $result['lng']);
    }

    public function test_process_keeps_zero_zero()
    {
        $result = (new GeoPoint)->process(['lat' => 0, 'lng' => 0]);

        $this->assertSame(0.0, $result['lat']);
        $this->assertSame(0.0, $result['lng']);
    }

    public function test_process_accepts_exact_bounds()
    {
        $this->assertNotNull((new GeoPoint)->process(['lat' => -90, 'lng' => -180]));
        $this->assertNotNull((new GeoPoint)->process(['lat' => 90, 'lng' => 180]));
    }

    #[DataProvider('emptyOrInvalidValues')]
    public function test_process_collapses_empty_and_invalid_values_to_null($value)
    {
        $this->assertNull((new GeoPoint)->process($value));
    }

    public static function emptyOrInvalidValues(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'empty array' => [[]],
            'lat without lng' => [['lat' => 38.7223]],
            'lng without lat' => [['lng' => -9.1393]],
            'non-numeric' => [['lat' => 'abc', 'lng' => 'def']],
            'lat above range' => [['lat' => 90.1, 'lng' => 0]],
            'lat below range' => [['lat' => -90.1, 'lng' => 0]],
            'lng above range' => [['lat' => 0, 'lng' => 180.1]],
            'lng below range' => [['lat' => 0, 'lng' => -180.1]],
        ];
    }

    public function test_process_drops_unknown_keys()
    {
        $result = (new GeoPoint)->process(['lat' => 1.0, 'lng' => 2.0, 'injected' => 'x']);

        $this->assertArrayNotHasKey('injected', $result);
    }

    public function test_process_keeps_google_metadata()
    {
        $result = (new GeoPoint)->process([
            'lat' => 1.0, 'lng' => 2.0, 'place_id' => 'abc', 'formatted_address' => 'Rua X', 'name' => 'Farmácia X',
        ]);

        $this->assertSame('abc', $result['place_id']);
        $this->assertSame('Farmácia X', $result['name']);
    }

    public function test_preprocess_round_trips_a_stored_value()
    {
        $stored = (new GeoPoint)->process(['lat' => 38.7223, 'lng' => -9.1393]);

        $this->assertSame($stored, (new GeoPoint)->preProcess($stored));
    }

    public function test_augment_exposes_lat_and_lng()
    {
        $augmented = (new GeoPoint)->augment(['lat' => 38.7223, 'lng' => -9.1393]);

        $this->assertSame(38.7223, $augmented['lat']);
        $this->assertSame(-9.1393, $augmented['lng']);
    }

    public function test_augment_passes_null_through()
    {
        $this->assertNull((new GeoPoint)->augment(null));
    }

    public function test_augment_drops_unknown_keys()
    {
        $this->assertArrayNotHasKey('injected', (new GeoPoint)->augment(['lat' => 1.0, 'lng' => 2.0, 'injected' => 'x']));
    }
}
