<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google API key
    |--------------------------------------------------------------------------
    |
    | Used server-side only, by this addon's CP proxy routes. It is never sent
    | to the browser. Leave it null and the Google modes degrade to Nominatim.
    |
    */

    'google_api_key' => env('GOOGLE_MAPS_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Map tiles
    |--------------------------------------------------------------------------
    |
    | Defaults for every field; a blueprint can override them per field.
    |
    */

    'tile_url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',

    'tile_attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',

];
