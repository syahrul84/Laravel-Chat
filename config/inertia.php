<?php

// Only what differs from the package defaults (vendor/inertiajs/inertia-laravel/config/inertia.php).
return [

    'pages' => [

        'ensure_pages_exist' => false,

        // Inertia 3 defaults to "js/pages"; this app uses "js/Pages". Case matters on Linux (CI),
        // so assertInertia() can't find the components there without this.
        'paths' => [

            resource_path('js/Pages'),

        ],

        'extensions' => [

            'vue',

        ],

    ],

];
