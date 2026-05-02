<?php

namespace Marvel\Console\Commands;

use Marvel\Services\ElasticsearchService;
use Marvel\Database\Models\Product;
use Illuminate\Console\Command;

class ElasticsearchVerify extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elasticsearch:verify {--detailed : Show detailed information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify Elasticsearch setup, indexing, and search functionality';

    protected ElasticsearchService $elasticsearch;

    /**
     * Create a new command instance.
     */
    public function __construct(ElasticsearchService $elasticsearch)
    {
        parent::__construct();
        $this->elasticsearch = $elasticsearch;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Elasticsearch Verification Script');
        $this->info('=====================================');
        $this->newLine();

        $issues = [];
        $warnings = [];

        // 1. Check Elasticsearch connection
        $this->info('1️⃣  Checking Elasticsearch connection...');
        $health = $this->elasticsearch->getClusterHealth();
        if (!$health) {
            $issues[] = '❌ Cannot connect to Elasticsearch cluster';
            $this->error('   ❌ Connection failed!');
        } else {
            $status = $health['status'];
            $this->line("   ✅ Connected to cluster: {$health['cluster_name']}");
            $this->line("   Status: {$status} | Nodes: {$health['number_of_nodes']}");
            if ($status === 'red') {
                $issues[] = '❌ Cluster status is RED';
            } elseif ($status === 'yellow') {
                $warnings[] = '⚠️  Cluster status is YELLOW (normal for single-node)';
            }
        }
        $this->newLine();

        // 2. Check indices
        $this->info('2️⃣  Checking indices...');
        $indices = config('elasticsearch.indices');
        $indexStatus = [];
        
        foreach ($indices as $indexType => $config) {
            $indexName = $config['name'];
            $exists = $this->elasticsearch->indexExists($indexName);
            
            if ($exists) {
                $stats = $this->elasticsearch->getIndexStats($indexName);
                $docCount = $stats['indices'][$indexName]['total']['docs']['count'] ?? 0;
                $indexStatus[$indexType] = [
                    'exists' => true,
                    'documents' => $docCount,
                ];
                
                $this->line("   ✅ {$indexName}: {$docCount} documents");
                
                // Check if index is empty when it shouldn't be
                if ($indexType === 'products' && $docCount === 0) {
                    $issues[] = "❌ Index {$indexName} exists but is empty";
                }
            } else {
                $indexStatus[$indexType] = ['exists' => false, 'documents' => 0];
                $this->line("   ❌ {$indexName}: Not created");
                $issues[] = "❌ Index {$indexName} does not exist";
            }
        }
        $this->newLine();

        // 3. Check database vs indexed documents
        $this->info('3️⃣  Checking database vs indexed documents...');
        $dbProductCount = Product::where('status', 'publish')->count();
        $indexedProductCount = $indexStatus['products']['documents'] ?? 0;
        
        $this->line("   Database products (published): {$dbProductCount}");
        $this->line("   Indexed products: {$indexedProductCount}");
        
        if ($dbProductCount > 0 && $indexedProductCount === 0) {
            $issues[] = "❌ No products indexed (DB has {$dbProductCount} products)";
        } elseif ($indexedProductCount < $dbProductCount * 0.9) {
            $warnings[] = "⚠️  Only {$indexedProductCount} of {$dbProductCount} products indexed (~" . round(($indexedProductCount / $dbProductCount) * 100) . "%)";
        } elseif ($indexedProductCount > 0) {
            $this->line("   ✅ Indexing coverage: " . round(($indexedProductCount / max($dbProductCount, 1)) * 100) . "%");
        }
        $this->newLine();

        // 4. Check mappings
        $this->info('4️⃣  Checking index mappings...');
        if (isset($indexStatus['products']['exists']) && $indexStatus['products']['exists']) {
            try {
                $client = $this->elasticsearch->getClient();
                $mapping = $client->indices()->getMapping(['index' => config('elasticsearch.indices.products.name')]);
                $mappingArray = $mapping->asArray();
                $indexName = config('elasticsearch.indices.products.name');
                
                $properties = $mappingArray[$indexName]['mappings']['properties'] ?? [];
                
                // Check for autocomplete field
                if (isset($properties['name']['fields']['autocomplete'])) {
                    $this->line("   ✅ Autocomplete field exists in mapping");
                } else {
                    $issues[] = "❌ Autocomplete field missing in mapping";
                    $this->line("   ❌ Autocomplete field missing");
                }
                
                // Check for nested fields
                if (isset($properties['tags']['type']) && $properties['tags']['type'] === 'nested') {
                    $this->line("   ✅ Tags nested field configured");
                } else {
                    $issues[] = "❌ Tags nested field not configured";
                }
                
                if (isset($properties['categories']['type']) && $properties['categories']['type'] === 'nested') {
                    $this->line("   ✅ Categories nested field configured");
                } else {
                    $issues[] = "❌ Categories nested field not configured";
                }
            } catch (\Exception $e) {
                $issues[] = "❌ Failed to check mappings: " . $e->getMessage();
                $this->error("   ❌ Error: " . $e->getMessage());
            }
        } else {
            $this->line("   ⚠️  Cannot check mappings - index doesn't exist");
        }
        $this->newLine();

        // 5. Test search functionality
        $this->info('5️⃣  Testing search functionality...');
        if (isset($indexStatus['products']['exists']) && $indexStatus['products']['exists'] && $indexStatus['products']['documents'] > 0) {
            $indexName = config('elasticsearch.indices.products.name');
            
            // Test basic search
            $testQuery = [
                'query' => [
                    'match' => [
                        'name' => 'тест'
                    ]
                ],
                'size' => 5
            ];
            
            try {
                $results = $this->elasticsearch->search($indexName, $testQuery, 0, 5);
                $hits = $results['hits']['hits'] ?? [];
                $total = $results['hits']['total']['value'] ?? 0;
                
                $this->line("   ✅ Basic search works: {$total} results for 'тест'");
                
                // Test autocomplete query
                $autocompleteQuery = [
                    'query' => [
                        'match' => [
                            'name.autocomplete' => 'те'
                        ]
                    ],
                    'size' => 5
                ];
                
                try {
                    $autocompleteResults = $this->elasticsearch->search($indexName, $autocompleteQuery, 0, 5);
                    $autocompleteTotal = $autocompleteResults['hits']['total']['value'] ?? 0;
                    $this->line("   ✅ Autocomplete search works: {$autocompleteTotal} results for 'те'");
                } catch (\Exception $e) {
                    $issues[] = "❌ Autocomplete search failed: " . $e->getMessage();
                    $this->error("   ❌ Autocomplete search failed: " . $e->getMessage());
                }
            } catch (\Exception $e) {
                $issues[] = "❌ Basic search failed: " . $e->getMessage();
                $this->error("   ❌ Search failed: " . $e->getMessage());
            }
        } else {
            $this->line("   ⚠️  Cannot test search - no indexed documents");
        }
        $this->newLine();

        // 6. Check API endpoints (if detailed)
        if ($this->option('detailed')) {
            $this->info('6️⃣  Checking API endpoints...');
            $baseUrl = config('app.url', 'https://api.sancan.ru');
            
            $endpoints = [
                '/api/search?q=тест&type=products' => 'Search endpoint',
                '/api/search/autocomplete?q=те&type=products' => 'Autocomplete endpoint',
                '/api/search/suggestions?q=тест&type=products' => 'Suggestions endpoint',
            ];
            
            foreach ($endpoints as $endpoint => $name) {
                $url = $baseUrl . $endpoint;
                $this->line("   Testing: {$name}");
                $this->line("   URL: {$url}");
                // Note: Actual HTTP testing would require curl or guzzle
                $this->line("   ℹ️  Use: curl \"{$url}\" to test manually");
            }
            $this->newLine();
        }

        // Summary
        $this->info('📊 Verification Summary');
        $this->info('======================');
        $this->newLine();

        if (empty($issues) && empty($warnings)) {
            $this->info('✅ All checks passed! Elasticsearch is properly configured.');
        } else {
            if (!empty($issues)) {
                $this->error('❌ Issues found:');
                foreach ($issues as $issue) {
                    $this->line("   {$issue}");
                }
                $this->newLine();
            }
            
            if (!empty($warnings)) {
                $this->warn('⚠️  Warnings:');
                foreach ($warnings as $warning) {
                    $this->line("   {$warning}");
                }
                $this->newLine();
            }
        }

        // Recommendations
        $this->info('💡 Recommendations:');
        $this->newLine();
        
        if (!empty($issues)) {
            if (in_array('Cannot connect to Elasticsearch cluster', $issues)) {
                $this->line('   1. Check Elasticsearch is running: sudo systemctl status elasticsearch');
                $this->line('   2. Check .env configuration: ELASTICSEARCH_HOST, ELASTICSEARCH_PORT');
            }
            
            if (in_array('Index sancan_products does not exist', $issues)) {
                $this->line('   1. Create indices: php artisan elasticsearch:setup');
            }
            
            if (strpos(implode(' ', $issues), 'No products indexed') !== false) {
                $this->line('   1. Index products: php artisan elasticsearch:index-products');
            }
            
            if (strpos(implode(' ', $issues), 'Autocomplete field missing') !== false) {
                $this->line('   1. Recreate index with proper mapping: php artisan elasticsearch:setup --recreate');
                $this->line('   2. Re-index products: php artisan elasticsearch:index-products --fresh');
            }
        } else {
            $this->line('   ✅ Everything looks good!');
            $this->line('   You can test search endpoints:');
            $this->line('   - curl "https://api.sancan.ru/search?q=test&type=products"');
            $this->line('   - curl "https://api.sancan.ru/search/autocomplete?q=te&type=products"');
        }

        return empty($issues) ? 0 : 1;
    }
}








