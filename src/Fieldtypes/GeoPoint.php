<?php

namespace Estouai\MapPoint\Fieldtypes;

use Statamic\Fields\Fieldtype;

class GeoPoint extends Fieldtype
{
    protected $categories = ['special'];

    protected $keywords = ['map', 'location', 'coordinates', 'latitude', 'longitude', 'geo'];

    protected function configFieldItems(): array
    {
        return [
            [
                'display' => __('Input Behavior'),
                'fields' => [
                    'mode' => [
                        'display' => __('Mode'),
                        'instructions' => __('How the editor picks a location. The Google modes need GOOGLE_MAPS_API_KEY set, and fall back to OpenStreetMap search without it.'),
                        'type' => 'select',
                        'default' => 'leaflet_map',
                        'options' => [
                            'google_poi' => __('Google place search'),
                            'google_map' => __('Map picker, Google search'),
                            'leaflet_map' => __('Map picker, OpenStreetMap search'),
                        ],
                    ],
                    'default_latitude' => [
                        'display' => __('Default latitude'),
                        'instructions' => __('Where the map opens when the field is empty.'),
                        'type' => 'float',
                        'default' => 39.5,
                        'width' => 50,
                    ],
                    'default_longitude' => [
                        'display' => __('Default longitude'),
                        'type' => 'float',
                        'default' => -8.0,
                        'width' => 50,
                    ],
                    'default_zoom' => [
                        'display' => __('Default zoom'),
                        'type' => 'integer',
                        'default' => 6,
                        'width' => 50,
                    ],
                ],
            ],
            [
                'display' => __('Appearance'),
                'fields' => [
                    'tile_url' => [
                        'display' => __('Tile URL'),
                        'instructions' => __('Leave empty to use the addon default (OpenStreetMap).'),
                        'type' => 'text',
                        'placeholder' => config('map-point.tile_url'),
                    ],
                    'tile_attribution' => [
                        'display' => __('Tile attribution'),
                        'type' => 'text',
                    ],
                ],
            ],
        ];
    }

    public function preload(): array
    {
        return [
            'tile_url' => $this->config('tile_url') ?: config('map-point.tile_url'),
            'tile_attribution' => $this->config('tile_attribution') ?: config('map-point.tile_attribution'),
            'default_latitude' => (float) ($this->config('default_latitude') ?? 39.5),
            'default_longitude' => (float) ($this->config('default_longitude') ?? -8.0),
            'default_zoom' => (int) ($this->config('default_zoom') ?? 6),
            // Whether Google is usable at all — never the key itself.
            'google_available' => filled(config('map-point.google_api_key')),
        ];
    }

    public function process($data)
    {
        return $this->normalize($data);
    }

    public function preProcess($data)
    {
        return $this->normalize($data);
    }

    /**
     * Without this, `required` sees ['lat' => null, 'lng' => null] as a
     * non-empty array and passes on a field with no pin set.
     */
    public function preProcessValidatable($value)
    {
        return $this->normalize($value);
    }

    public function augment($value)
    {
        return $this->normalize($value);
    }

    /**
     * Collapse anything that isn't a complete, in-range coordinate to null.
     *
     * Note on emptiness: 0 is a valid coordinate (Null Island is a real point)
     * and '' is not, so this leans on is_numeric and array_key_exists rather
     * than empty() or a bare truthiness test.
     */
    private function normalize($value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $lat = $value['lat'] ?? null;
        $lng = $value['lng'] ?? null;

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
            'place_id' => $value['place_id'] ?? null,
            'formatted_address' => $value['formatted_address'] ?? null,
            'name' => $value['name'] ?? null,
        ];
    }
}
