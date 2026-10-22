<?php

namespace Estouai\MapPoint\Geocoders;

interface Geocoder
{
    /**
     * @return array<int, array{name: string, formatted_address: string, lat: ?float, lng: ?float, place_id: ?string}>
     *
     * @throws \RuntimeException on any failure, so callers have one thing to catch.
     */
    public function search(string $query): array;
}
