<?php

namespace Estouai\MapPoint\Geocoders;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GooglePlaces implements Geocoder
{
    public function __construct(private readonly string $key) {}

    public function search(string $query): array
    {
        $data = $this->get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
            'input' => $query,
            'key' => $this->key,
        ]);

        return collect($data['predictions'] ?? [])->map(fn ($prediction) => [
            'name' => $prediction['structured_formatting']['main_text'] ?? ($prediction['description'] ?? ''),
            'formatted_address' => $prediction['structured_formatting']['secondary_text'] ?? '',
            // Autocomplete has no coordinates; details() resolves them.
            'lat' => null,
            'lng' => null,
            'place_id' => $prediction['place_id'] ?? null,
        ])->all();
    }

    public function details(string $placeId): ?array
    {
        $data = $this->get('https://maps.googleapis.com/maps/api/place/details/json', [
            'place_id' => $placeId,
            'fields' => 'name,formatted_address,geometry',
            'key' => $this->key,
        ]);

        if (! $result = $data['result'] ?? null) {
            return null;
        }

        $location = $result['geometry']['location'] ?? [];

        return [
            'name' => $result['name'] ?? '',
            'formatted_address' => $result['formatted_address'] ?? '',
            'lat' => isset($location['lat']) ? (float) $location['lat'] : null,
            'lng' => isset($location['lng']) ? (float) $location['lng'] : null,
            'place_id' => $placeId,
        ];
    }

    /**
     * Google reports its own errors as HTTP 200 with an error `status`, so the
     * HTTP code alone is not enough to tell success from failure.
     */
    private function get(string $url, array $query): array
    {
        try {
            $response = Http::timeout(5)->get($url, $query);
        } catch (\Throwable $e) {
            throw new RuntimeException('Google request failed: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new RuntimeException('Google returned HTTP '.$response->status());
        }

        $data = $response->json() ?? [];
        $status = $data['status'] ?? 'UNKNOWN_ERROR';

        if (! in_array($status, ['OK', 'ZERO_RESULTS'], true)) {
            throw new RuntimeException('Google returned status '.$status);
        }

        return $data;
    }
}
