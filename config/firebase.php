<?php

return [

    'project_id' => env('FIREBASE_PROJECT_ID'),

    'credentials' => [
        'file' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase/serviceAccountKey.json')),
    ],

    'fcm' => [
        'database_url' => env('FIREBASE_DATABASE_URL'),
        'default_sound' => 'default',
        'default_icon' => '@mipmap/ic_launcher',
        'default_color' => '#4CAF50',
    ],

];
