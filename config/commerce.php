<?php

return [
    'use_built_assets' => (bool) env('COMMERCE_USE_BUILT_ASSETS', false),
    'contact' => env('COMMERCE_CONTACT', ''),
    'shipping_flat_idr' => (int) env('COMMERCE_SHIPPING_FLAT_IDR', 20000),
    'payment_window_hours' => 24,
    'manual_payment_instructions' => env('COMMERCE_PAYMENT_INSTRUCTIONS', 'Hubungi pengelola toko untuk rekening transfer resmi. Jangan transfer sebelum rekening dikonfirmasi.'),
];
