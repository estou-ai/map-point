<?php

namespace Estouai\MapPoint\Geocoders;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Places API (New). The legacy place/autocomplete/json and place/details/json
 * endpoints can no longer be enabled on new Google Cloud projects, so this
 * talks to places.googleapis.com/v1 instead.
 */
class GooglePlaces implements Geocoder
{
    public function __construct(private readonly string $key) {}

    public function search(string $query): array
    {
        $data = $this->request(
            'post',
            'https://places.googleapis.com/v1/places:autocomplete',
            'suggestions.placePrediction.placeId,suggestions.placePrediction.text,suggestions.placePrediction.structuredFormat',
            ['input' => $query],
        );

        $rows = collect($data['suggestions'] ?? [])
            ->pluck('placePrediction')
            ->filter()
            ->map(fn ($prediction) => [
                'name' => $prediction['structuredFormat']['mainText']['text']
                    ?? ($prediction['text']['text'] ?? ''),
                'formatted_address' => $prediction['structuredFormat']['secondaryText']['text'] ?? '',
                'lat' => null,
                'lng' => null,
                'place_id' => $prediction['placeId'] ?? null,
            ])
            ->values()
            ->all();

        if ($rows === []) {
            throw new RuntimeException('Google returned no suggestions.');
        }

        return $rows;
    }

    public function details(string $placeId): ?array
    {
        $data = $this->request(
            'get',
            'https://places.googleapis.com/v1/places/'.urlencode($placeId),
            'displayName,formattedAddress,location',
        );

        $location = $data['location'] ?? [];

        if (! isset($location['latitude'], $location['longitude'])) {
            return null;
        }

        return [
            'name' => $data['displayName']['text'] ?? '',
            'formatted_address' => $data['formattedAddress'] ?? '',
            'lat' => (float) $location['latitude'],
            'lng' => (float) $location['longitude'],
            'place_id' => $placeId,
        ];
    }

    private function request(string $method, string $url, string $fieldMask, array $body = []): array
    {
        try {
            $request = Http::timeout(5)->withHeaders([
                'X-Goog-Api-Key' => $this->key,
                'X-Goog-FieldMask' => $fieldMask,
            ]);

            $response = $method === 'post'
                ? $request->asJson()->post($url, $body)
                : $request->get($url);
        } catch (\Throwable) {
            // Never pass the original exception through: its message can carry
            // the request URL, and with it the API key.
            throw new RuntimeException('Google request failed.');
        }

        if ($response->failed()) {
            $data = $response->json() ?? [];

            throw new RuntimeException(
                'Google returned '.($data['error']['status'] ?? 'HTTP '.$response->status())
            );
        }

        return $response->json() ?? [];
    }
}
