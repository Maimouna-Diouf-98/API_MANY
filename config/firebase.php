<?php

return [
    'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase-credentials.json')),
    'project_id'  => env('FIREBASE_PROJECT_ID'),
];