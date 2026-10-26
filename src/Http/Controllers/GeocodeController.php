<?php

namespace Estouai\MapPoint\Http\Controllers;

use Estouai\MapPoint\Geocoders\GooglePlaces;
use Estouai\MapPoint\Geocoders\Nominatim;
use Illuminate\Http\Request;
use RuntimeException;

class GeocodeController
{
    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:200',
            'provider' => 'nullable|in:google,nominatim',
        ]);

        $query = trim($validated['q'] ?? '');

        if ($query === '') {
            return ['results' => [], 'fallback' => false];
        }

        $wantsGoogle = ($validated['provider'] ?? 'nominatim') === 'google';

        if ($wantsGoogle && $key = config('map-point.google_api_key')) {
            try {
                return ['results' => (new GooglePlaces($key))->search($query), 'fallback' => false];
            } catch (RuntimeException $e) {
                report($e);
            }
        }

        // Either Google was never asked for, has no key, or just failed. The
        // editor keeps a working search either way.
        try {
            return [
                'results' => (new Nominatim)->search($query),
                'fallback' => $wantsGoogle,
            ];
        } catch (RuntimeException $e) {
            report($e);

            return response()->json(['message' => __('Address search is unavailable.')], 503);
        }
    }

    public function details(Request $request)
    {
        $validated = $request->validate(['place_id' => 'required|string|max:400']);

        // Nominatim has no place-ID concept, so there is nothing to fall back to.
        if (! $key = config('map-point.google_api_key')) {
            return response()->json(['message' => __('Google place lookup is not configured.')], 503);
        }

        try {
            $row = (new GooglePlaces($key))->details($validated['place_id']);
        } catch (RuntimeException $e) {
            report($e);

            return response()->json(['message' => __('Google place lookup failed.')], 503);
        }

        return ['results' => array_filter([$row]), 'fallback' => false];
    }
}
