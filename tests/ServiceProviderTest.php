<?php

namespace Estouai\MapPoint\Tests;

class ServiceProviderTest extends TestCase
{
    public function test_config_defaults_are_registered()
    {
        $this->assertSame('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', config('map-point.tile_url'));
        $this->assertStringContainsString('OpenStreetMap', config('map-point.tile_attribution'));
    }

    public function test_google_api_key_defaults_to_null()
    {
        $this->assertNull(config('map-point.google_api_key'));
    }
}
