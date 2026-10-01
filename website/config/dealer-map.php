<?php
// Providers can be changed through environment configuration without editing JS.
return [
    'searchUrl' => env('DEALER_GEOCODER_URL', 'https://photon.komoot.io/'),
    'tileUrl' => env('DEALER_MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
    'attribution' => env('DEALER_MAP_ATTRIBUTION', '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'),
];
