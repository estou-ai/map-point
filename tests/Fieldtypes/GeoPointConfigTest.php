<?php

namespace Estouai\MapPoint\Tests\Fieldtypes;

use Estouai\MapPoint\Fieldtypes\GeoPoint;
use Estouai\MapPoint\Tests\TestCase;
use Statamic\Fields\Field;

class GeoPointConfigTest extends TestCase
{
    public function test_config_sections_expose_the_three_modes()
    {
        $fields = (new GeoPoint)->configFields()->all();

        $this->assertSame(
            ['google_poi', 'google_map', 'leaflet_map'],
            array_keys($fields['mode']->get('options'))
        );
        $this->assertSame('leaflet_map', $fields['mode']->get('default'));
        $this->assertSame(39.5, $fields['default_latitude']->get('default'));
        $this->assertSame(-8.0, $fields['default_longitude']->get('default'));
        $this->assertSame(6, $fields['default_zoom']->get('default'));
    }

    public function test_preload_reports_google_unavailable_without_a_key()
    {
        config(['map-point.google_api_key' => null]);

        $this->assertFalse($this->fieldtype()->preload()['google_available']);
    }

    public function test_preload_reports_google_available_with_a_key()
    {
        config(['map-point.google_api_key' => 'test-key']);

        $this->assertTrue($this->fieldtype()->preload()['google_available']);
    }

    public function test_preload_never_exposes_the_api_key()
    {
        config(['map-point.google_api_key' => 'super-secret-key']);

        $this->assertStringNotContainsString('super-secret-key', json_encode($this->fieldtype()->preload()));
    }

    public function test_preload_falls_back_to_configured_tiles()
    {
        $this->assertSame(config('map-point.tile_url'), $this->fieldtype()->preload()['tile_url']);
    }

    public function test_preload_defaults_to_portugal()
    {
        $preload = $this->fieldtype()->preload();

        $this->assertSame(39.5, $preload['default_latitude']);
        $this->assertSame(-8.0, $preload['default_longitude']);
        $this->assertSame(6, $preload['default_zoom']);
    }

    public function test_preload_honours_field_level_overrides()
    {
        $preload = $this->fieldtype(['tile_url' => 'https://example.test/{z}/{x}/{y}.png', 'default_zoom' => 12])->preload();

        $this->assertSame('https://example.test/{z}/{x}/{y}.png', $preload['tile_url']);
        $this->assertSame(12, $preload['default_zoom']);
    }

    public function test_an_empty_value_fails_a_required_field()
    {
        $field = new Field('coordinates', ['type' => 'geo_point', 'required' => true]);

        $this->assertTrue($field->isRequired());
        $this->assertNull($field->fieldtype()->preProcessValidatable(['lat' => null, 'lng' => null]));
    }

    public function test_a_set_pin_survives_validatable_preprocessing()
    {
        $field = new Field('coordinates', ['type' => 'geo_point', 'required' => true]);

        $this->assertNotNull($field->fieldtype()->preProcessValidatable(['lat' => 38.7223, 'lng' => -9.1393]));
    }

    private function fieldtype(array $config = []): GeoPoint
    {
        return (new Field('coordinates', ['type' => 'geo_point'] + $config))->fieldtype();
    }
}
