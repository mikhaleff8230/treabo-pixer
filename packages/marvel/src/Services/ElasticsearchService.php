<?php

namespace Marvel\Services;

use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ElasticsearchService
{
    protected Client $client;
    protected array $config;

    public function __construct()
    {
        $this->config = config('elasticsearch');
        $this->client = $this->createClient();
    }

    /**
     * Create Elasticsearch client
     */
    protected function createClient(): Client
    {
        $hosts = $this->config['hosts'];
        
        // Для Elasticsearch v8 используем правильный формат
        $clientBuilder = ClientBuilder::create();
        
        // Устанавливаем хосты в правильном формате для v8
        $hostConfigs = [];
        foreach ($hosts as $host) {
            $hostConfigs[] = $host['scheme'] . '://' . $host['host'] . ':' . $host['port'];
        }
        
        $clientBuilder->setHosts($hostConfigs);
        $clientBuilder->setRetries($this->config['performance']['retries'] ?? 2);

        // SSL настройки (если включен https)
        if ($hosts[0]['scheme'] === 'https') {
            $clientBuilder->setSSLVerification(false); // Для production настроить правильно
        }

        return $clientBuilder->build();
    }

    /**
     * Get Elasticsearch client instance
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Create an index
     */
    public function createIndex(string $indexType): bool
    {
        try {
            $indexConfig = $this->config['indices'][$indexType];
            $indexName = $indexConfig['name'];

            // Проверяем существование индекса
            if ($this->indexExists($indexName)) {
                Log::info("Index {$indexName} already exists");
                return true;
            }

            // Получаем маппинг для индекса
            $mappings = $this->getMappings($indexType);

            $params = [
                'index' => $indexName,
                'body' => [
                    'settings' => $indexConfig['settings'],
                    'mappings' => $mappings,
                ],
            ];

            $response = $this->client->indices()->create($params);

            Log::info("Index {$indexName} created successfully", [
                'response' => $response->asArray()
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to create index {$indexType}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Delete an index
     */
    public function deleteIndex(string $indexName): bool
    {
        try {
            if (!$this->indexExists($indexName)) {
                return true;
            }

            $this->client->indices()->delete(['index' => $indexName]);
            
            Log::info("Index {$indexName} deleted successfully");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete index {$indexName}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if index exists
     */
    public function indexExists(string $indexName): bool
    {
        try {
            return $this->client->indices()->exists(['index' => $indexName])->asBool();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Index a document
     */
    public function indexDocument(string $indexName, string $id, array $body): bool
    {
        try {
            $params = [
                'index' => $indexName,
                'id' => $id,
                'body' => $body,
            ];

            $this->client->index($params);
            
            if ($this->config['logging']['log_queries'] ?? false) {
                Log::debug("Document indexed", [
                    'index' => $indexName,
                    'id' => $id
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to index document", [
                'index' => $indexName,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Update a document
     */
    public function updateDocument(string $indexName, string $id, array $body): bool
    {
        try {
            $params = [
                'index' => $indexName,
                'id' => $id,
                'body' => [
                    'doc' => $body,
                    'doc_as_upsert' => true,
                ],
            ];

            $this->client->update($params);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to update document", [
                'index' => $indexName,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Delete a document
     */
    public function deleteDocument(string $indexName, string $id): bool
    {
        try {
            $params = [
                'index' => $indexName,
                'id' => $id,
            ];

            $this->client->delete($params);
            return true;
        } catch (\Exception $e) {
            // Document might not exist, which is fine
            if (strpos($e->getMessage(), '404') === false) {
                Log::error("Failed to delete document", [
                    'index' => $indexName,
                    'id' => $id,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }

    /**
     * Bulk index documents
     */
    public function bulkIndex(string $indexName, array $documents): bool
    {
        try {
            $params = ['body' => []];

            foreach ($documents as $doc) {
                $params['body'][] = [
                    'index' => [
                        '_index' => $indexName,
                        '_id' => $doc['id'],
                    ]
                ];
                
                unset($doc['id']);
                $params['body'][] = $doc;
            }

            $response = $this->client->bulk($params);
            $responseArray = $response->asArray();

            if ($responseArray['errors']) {
                Log::warning("Bulk indexing completed with errors", [
                    'index' => $indexName,
                    'items' => count($documents)
                ]);
            } else {
                Log::info("Bulk indexing successful", [
                    'index' => $indexName,
                    'items' => count($documents)
                ]);
            }

            return !$responseArray['errors'];
        } catch (\Exception $e) {
            Log::error("Bulk indexing failed", [
                'index' => $indexName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Search documents
     */
    public function search(string $indexName, array $query, int $from = 0, int $size = 20): array
    {
        try {
            $params = [
                'index' => $indexName,
                'body' => $query,
                'from' => $from,
                'size' => $size,
            ];

            $response = $this->client->search($params);
            $result = $response->asArray();

            if ($this->config['logging']['log_queries'] ?? false) {
                Log::debug("Search executed", [
                    'index' => $indexName,
                    'query' => $query,
                    'hits' => $result['hits']['total']['value'] ?? 0
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error("Search failed", [
                'index' => $indexName,
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            return ['hits' => ['hits' => [], 'total' => ['value' => 0]]];
        }
    }

    /**
     * Get document by ID
     */
    public function getDocument(string $indexName, string $id): ?array
    {
        try {
            $params = [
                'index' => $indexName,
                'id' => $id,
            ];

            $response = $this->client->get($params);
            return $response->asArray()['_source'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Refresh index (make all operations available for search)
     */
    public function refreshIndex(string $indexName): bool
    {
        try {
            $this->client->indices()->refresh(['index' => $indexName]);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to refresh index", [
                'index' => $indexName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get index statistics
     */
    public function getIndexStats(string $indexName): ?array
    {
        try {
            $response = $this->client->indices()->stats(['index' => $indexName]);
            return $response->asArray();
        } catch (\Exception $e) {
            Log::error("Failed to get index stats", [
                'index' => $indexName,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get cluster health
     */
    public function getClusterHealth(): ?array
    {
        try {
            $response = $this->client->cluster()->health();
            return $response->asArray();
        } catch (\Exception $e) {
            Log::error("Failed to get cluster health", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get mappings for a specific index type
     */
    protected function getMappings(string $indexType): array
    {
        switch ($indexType) {
            case 'products':
                return $this->getProductMappings();
            case 'places':
                return $this->getPlaceMappings();
            case 'categories':
                return $this->getCategoryMappings();
            case 'shops':
                return $this->getShopMappings();
            default:
                return [];
        }
    }

    /**
     * Product mappings
     */
    protected function getProductMappings(): array
    {
        return [
            'properties' => [
                'id' => ['type' => 'long'],
                'name' => [
                    'type' => 'text',
                    'analyzer' => 'russian_analyzer',
                    'fields' => [
                        'autocomplete' => [
                            'type' => 'text',
                            'analyzer' => 'autocomplete_analyzer',
                            'search_analyzer' => 'search_analyzer',
                        ],
                        'keyword' => [
                            'type' => 'keyword',
                        ],
                    ],
                ],
                'slug' => ['type' => 'keyword'],
                'description' => [
                    'type' => 'text',
                    'analyzer' => 'russian_analyzer',
                ],
                'price' => ['type' => 'float'],
                'sale_price' => ['type' => 'float'],
                'quantity' => ['type' => 'integer'],
                'in_stock' => ['type' => 'boolean'],
                'sku' => [
                    'type' => 'text',
                    'fields' => [
                        'keyword' => ['type' => 'keyword'],
                    ],
                ],
                'status' => ['type' => 'keyword'],
                'product_type' => ['type' => 'keyword'],
                'language' => ['type' => 'keyword'],
                
                'shop' => [
                    'properties' => [
                        'id' => ['type' => 'long'],
                        'name' => ['type' => 'text'],
                        'slug' => ['type' => 'keyword'],
                    ],
                ],
                
                'categories' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => ['type' => 'long'],
                        'name' => ['type' => 'text'],
                        'slug' => ['type' => 'keyword'],
                    ],
                ],
                
                'tags' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => ['type' => 'long'],
                        'name' => ['type' => 'keyword'],
                        'slug' => ['type' => 'keyword'],
                    ],
                ],
                
                'author' => [
                    'properties' => [
                        'id' => ['type' => 'long'],
                        'name' => ['type' => 'text'],
                    ],
                ],
                
                'manufacturer' => [
                    'properties' => [
                        'id' => ['type' => 'long'],
                        'name' => ['type' => 'text'],
                    ],
                ],
                
                'ratings' => ['type' => 'float'],
                'total_reviews' => ['type' => 'integer'],
                'created_at' => ['type' => 'date'],
                'updated_at' => ['type' => 'date'],
            ],
        ];
    }

    /**
     * Place mappings
     */
    protected function getPlaceMappings(): array
    {
        return [
            'properties' => [
                'id' => ['type' => 'long'],
                'title' => [
                    'type' => 'text',
                    'analyzer' => 'russian_analyzer',
                    'fields' => [
                        'keyword' => ['type' => 'keyword'],
                    ],
                ],
                'description' => [
                    'type' => 'text',
                    'analyzer' => 'russian_analyzer',
                ],
                
                'user' => [
                    'properties' => [
                        'id' => ['type' => 'long'],
                        'name' => ['type' => 'text'],
                    ],
                ],
                
                'hashtags' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => ['type' => 'long'],
                        'name' => ['type' => 'keyword'],
                    ],
                ],
                
                'products' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => ['type' => 'long'],
                        'name' => ['type' => 'text'],
                    ],
                ],
                
                'images_count' => ['type' => 'integer'],
                'videos_count' => ['type' => 'integer'],
                'likes_count' => ['type' => 'integer'],
                'followers_count' => ['type' => 'integer'],
                
                'created_at' => ['type' => 'date'],
                'updated_at' => ['type' => 'date'],
            ],
        ];
    }

    /**
     * Category mappings
     */
    protected function getCategoryMappings(): array
    {
        return [
            'properties' => [
                'id' => ['type' => 'long'],
                'name' => [
                    'type' => 'text',
                    'analyzer' => 'russian_analyzer',
                ],
                'slug' => ['type' => 'keyword'],
                'description' => ['type' => 'text'],
                'parent_id' => ['type' => 'long'],
            ],
        ];
    }

    /**
     * Shop mappings
     */
    protected function getShopMappings(): array
    {
        return [
            'properties' => [
                'id' => ['type' => 'long'],
                'name' => [
                    'type' => 'text',
                    'analyzer' => 'russian_analyzer',
                ],
                'slug' => ['type' => 'keyword'],
                'description' => ['type' => 'text'],
                'location' => ['type' => 'geo_point'],
            ],
        ];
    }
}


