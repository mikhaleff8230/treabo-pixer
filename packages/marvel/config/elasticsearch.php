<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Elasticsearch Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may specify the configuration for Elasticsearch integration.
    | This configuration will be used by the ElasticsearchService.
    |
    */

    'enabled' => env('ELASTICSEARCH_ENABLED', true),

    'hosts' => [
        [
            'host' => env('ELASTICSEARCH_HOST', 'localhost'),
            'port' => env('ELASTICSEARCH_PORT', 9200),
            'scheme' => env('ELASTICSEARCH_SCHEME', 'http'),
            'user' => env('ELASTICSEARCH_USER', null),
            'pass' => env('ELASTICSEARCH_PASSWORD', null),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Index Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the indices for different models.
    |
    */

    'index_prefix' => env('ELASTICSEARCH_INDEX_PREFIX', 'sancan_'),

    'indices' => [
        'products' => [
            'name' => env('ELASTICSEARCH_INDEX_PREFIX', 'sancan_') . 'products',
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 1,
                'analysis' => [
                    'analyzer' => [
                        'russian_analyzer' => [
                            'type' => 'custom',
                            'tokenizer' => 'standard',
                            'filter' => ['lowercase', 'russian_stop', 'russian_stemmer'],
                        ],
                        'autocomplete_analyzer' => [
                            'type' => 'custom',
                            'tokenizer' => 'standard',
                            'filter' => ['lowercase', 'edge_ngram_filter'],
                        ],
                        'search_analyzer' => [
                            'type' => 'custom',
                            'tokenizer' => 'standard',
                            'filter' => ['lowercase', 'russian_stop', 'russian_stemmer'],
                        ],
                    ],
                    'filter' => [
                        'russian_stop' => [
                            'type' => 'stop',
                            'stopwords' => '_russian_',
                        ],
                        'russian_stemmer' => [
                            'type' => 'stemmer',
                            'language' => 'russian',
                        ],
                        'edge_ngram_filter' => [
                            'type' => 'edge_ngram',
                            'min_gram' => 2,
                            'max_gram' => 20,
                        ],
                    ],
                ],
            ],
        ],

        'places' => [
            'name' => env('ELASTICSEARCH_INDEX_PREFIX', 'sancan_') . 'places',
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 1,
                'analysis' => [
                    'analyzer' => [
                        'russian_analyzer' => [
                            'type' => 'custom',
                            'tokenizer' => 'standard',
                            'filter' => ['lowercase', 'russian_stop', 'russian_stemmer'],
                        ],
                    ],
                    'filter' => [
                        'russian_stop' => [
                            'type' => 'stop',
                            'stopwords' => '_russian_',
                        ],
                        'russian_stemmer' => [
                            'type' => 'stemmer',
                            'language' => 'russian',
                        ],
                    ],
                ],
            ],
        ],

        'categories' => [
            'name' => env('ELASTICSEARCH_INDEX_PREFIX', 'sancan_') . 'categories',
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 1,
            ],
        ],

        'shops' => [
            'name' => env('ELASTICSEARCH_INDEX_PREFIX', 'sancan_') . 'shops',
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 1,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Configuration
    |--------------------------------------------------------------------------
    |
    | Configure search behavior.
    |
    */

    'search' => [
        'default_size' => 20,
        'max_size' => 100,
        'min_score' => 0.1,
        
        // Boosting для релевантности
        'boosting' => [
            'products' => [
                'name' => 3.0,
                'description' => 1.0,
                'sku' => 2.0,
                'categories.name' => 1.5,
                'tags.name' => 1.2,
            ],
            'places' => [
                'title' => 3.0,
                'description' => 1.0,
                'hashtags.name' => 2.0,
            ],
        ],

        // Фильтры по умолчанию
        'default_filters' => [
            'products' => [
                'status' => 'publish',
                'in_stock' => true,
            ],
        ],

        // Агрегации для фасетного поиска
        'aggregations' => [
            'products' => [
                'categories' => ['field' => 'categories.id', 'size' => 50],
                'price_ranges' => [
                    'ranges' => [
                        ['to' => 1000],
                        ['from' => 1000, 'to' => 5000],
                        ['from' => 5000, 'to' => 10000],
                        ['from' => 10000, 'to' => 50000],
                        ['from' => 50000],
                    ],
                ],
                'manufacturers' => ['field' => 'manufacturer.id', 'size' => 30],
                'in_stock' => ['field' => 'in_stock'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Should indexing operations be queued?
    |
    */

    'queue' => [
        'enabled' => env('ELASTICSEARCH_QUEUE', true),
        'connection' => env('QUEUE_CONNECTION', 'redis'),
        'queue_name' => 'elasticsearch',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging for search queries and errors.
    |
    */

    'logging' => [
        'enabled' => env('ELASTICSEARCH_LOGGING', true),
        'log_queries' => env('ELASTICSEARCH_LOG_QUERIES', false),
        'log_errors' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics
    |--------------------------------------------------------------------------
    |
    | Track search queries for analytics.
    |
    */

    'analytics' => [
        'enabled' => env('ELASTICSEARCH_ANALYTICS', true),
        'track_clicks' => true,
        'track_conversions' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Autocomplete Configuration
    |--------------------------------------------------------------------------
    |
    | Configure autocomplete behavior.
    |
    */

    'autocomplete' => [
        'min_length' => 2,
        'max_suggestions' => 10,
        'fields' => [
            'products' => ['name', 'sku'],
            'places' => ['title'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Suggestions Configuration
    |--------------------------------------------------------------------------
    |
    | "Did you mean?" suggestions configuration.
    |
    */

    'suggestions' => [
        'enabled' => true,
        'confidence' => 0.7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    |
    | Performance-related settings.
    |
    */

    'performance' => [
        'bulk_size' => env('ELASTICSEARCH_BULK_SIZE', 500),
        'timeout' => env('ELASTICSEARCH_TIMEOUT', 30),
        'retries' => env('ELASTICSEARCH_RETRIES', 2),
    ],
];


