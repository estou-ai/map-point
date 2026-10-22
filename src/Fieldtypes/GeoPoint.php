<?php

namespace Estouai\MapPoint\Fieldtypes;

use Statamic\Fields\Fieldtype;

class GeoPoint extends Fieldtype
{
    protected $categories = ['special'];

    protected $keywords = ['map', 'location', 'coordinates', 'latitude', 'longitude', 'geo'];

    public function process($data)
    {
        return $this->normalize($data);
    }

    public function preProcess($data)
    {
        return $this->normalize($data);
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
