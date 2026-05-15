<?php

return [
    'capacity' => (int) env('PARKING_CAPACITY', 6),
    'reserved_service_spots_count' => (int) env('PARKING_RESERVED_SERVICE_SPOTS_COUNT', 1),
    'manual_password' => (string) env('PARKING_MANUAL_PASSWORD', 'change-me'),
    'admin_token' => (string) env('PARKING_ADMIN_TOKEN', ''),
    'allowed_lat' => env('PARKING_ALLOWED_LAT'),
    'allowed_lng' => env('PARKING_ALLOWED_LNG'),
    'allowed_radius_meters' => env('PARKING_ALLOWED_RADIUS_METERS'),
];
