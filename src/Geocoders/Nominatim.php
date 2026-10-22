<?php

namespace Estouai\MapPoint\Geocoders;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class Nominatim implements Geocoder
{
    public function search(string $query): array
    {
        try {
            $response = Http::timeout(5)
                // Nominatim's usage policy requires a descriptive User-Agent.
                ->withHeaders(['User-Agent' => 'estouai/map-point Statamic addon'])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'json',
                    'q' => $query,
                    'limit' => 5,
                ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('Nominatim request failed: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new RuntimeException('Nominatim returned HTTP '.$response->status());
        }

        return collect($response->json() ?? [])->map(fn ($result) => [
            'name' => $result['display_name'] ?? '',
            'formatted_address' => $result['display_name'] ?? '',
            'lat' => isset($result['lat']) ? (float) $result['lat'] : null,
            'lng' => isset($result['lon']) ? (float) $result['lon'] : null,
            'place_id' => null,
        ])->all();
    }
}
