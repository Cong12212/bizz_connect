<?php

return [
    'default' => 'default',
    'documentations' => [
        'default' => [
            'api' => [
                'title' => 'Bizz Connect API Documentation',
            ],
            'routes' => [
                'api' => 'api/documentation',
            ],
            'paths' => [
                'docs' => storage_path('api-docs'),
                'docs_json' => 'api-docs.json',
                'annotations' => base_path('app'),
            ],
            'swagger_version' => env('SWAGGER_VERSION', '3.0'),
            'operations_sort' => env('L5_SWAGGER_OPERATIONS_SORT', null),
        ],
    ],
];
