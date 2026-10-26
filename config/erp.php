<?php

return [
    'single_user_mode' => true,
    'admin_default_name' => env('ADMIN_DEFAULT_NAME', 'System Administrator'),
    'admin_default_email' => env('ADMIN_DEFAULT_EMAIL', 'admin@erp.local'),
    'admin_default_password' => env('ADMIN_DEFAULT_PASSWORD'),
    'setup_secret_key' => env('SETUP_SECRET_KEY'),
    'provinces' => [
        'Sindh', 'Punjab', 'Khyber Pakhtunkhwa', 'Balochistan',
        'Islamabad Capital Territory', 'Gilgit-Baltistan', 'Azad Jammu and Kashmir',
    ],
];
