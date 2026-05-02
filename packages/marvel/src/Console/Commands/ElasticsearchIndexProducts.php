<?php

namespace Marvel\Console\Commands;

use Marvel\Services\ElasticsearchService;
use Marvel\Database\Models\Product;
use Illuminate\Console\Command;

class ElasticsearchIndexProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elasticsearch:index-products 
                            {--fresh : Delete and recreate index before indexing}
                            {--chunk=500 : Number of products to index at once}
                            {--status=publish : Only index products with specific status}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Index products into Elasticsearch';

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
        $this->info('🔍 Starting products indexing...');
        $this->newLine();

        $indexName = config('elasticsearch.indices.products.name');
        $chunkSize = (int) $this->option('chunk');
        $fresh = $this->option('fresh');

        // Check if index exists
        if (!$this->elasticsearch->indexExists($indexName)) {
            $this->error("Index '{$indexName}' does not exist!");
            $this->line('Run: <fg=green>php artisan elasticsearch:setup</>');
            return 1;
        }

        // Recreate index if fresh option
        if ($fresh) {
            if ($this->confirm("Delete and recreate index '{$indexName}'?", false)) {
                $this->warn('🗑️  Deleting existing index...');
                $this->elasticsearch->deleteIndex($indexName);
                
                $this->info('⚙️  Creating fresh index...');
                $this->elasticsearch->createIndex('products');
                $this->newLine();
            }
        }

        // Get total products count
        $query = Product::query()
            ->with(['shop', 'categories', 'tags', 'author', 'manufacturer']);

        // Filter by status if specified
        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        $total = $query->count();

        if ($total === 0) {
            $this->warn('No products found to index.');
            return 0;
        }

        $this->info("📊 Total products to index: {$total}");
        $this->newLine();

        // Progress bar
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');
        $bar->setMessage('Starting...');
        $bar->start();

        $indexed = 0;
        $errors = 0;

        // Process in chunks
        $query->chunk($chunkSize, function ($products) use ($indexName, $bar, &$indexed, &$errors) {
            $batch = [];

            foreach ($products as $product) {
                try {
                    $batch[] = $this->transformProduct($product);
                    $indexed++;
                } catch (\Exception $e) {
                    $errors++;
                    \Log::error('Failed to transform product', [
                        'product_id' => $product->id,
                        'error' => $e->getMessage()
                    ]);
                }
                
                $bar->setMessage("Indexing... (Errors: {$errors})");
                $bar->advance();
            }

            // Bulk index batch
            if (!empty($batch)) {
                try {
                    $this->elasticsearch->bulkIndex($indexName, $batch);
                } catch (\Exception $e) {
                    $errors += count($batch);
                    \Log::error('Bulk indexing failed', [
                        'batch_size' => count($batch),
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Small delay to prevent overwhelming Elasticsearch
            usleep(100000); // 100ms
        });

        $bar->finish();
        $this->newLine(2);

        // Refresh index to make documents searchable
        $this->info('🔄 Refreshing index...');
        $this->elasticsearch->refreshIndex($indexName);

        // Summary
        $this->displaySummary($indexed, $errors, $indexName);

        return 0;
    }

    /**
     * Transform product model to Elasticsearch document
     */
    protected function transformProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name ?? '',
            'slug' => $product->slug ?? '',
            'description' => strip_tags($product->description ?? ''),
            'price' => (float) ($product->price ?? 0),
            'sale_price' => (float) ($product->sale_price ?? 0),
            'quantity' => (int) ($product->quantity ?? 0),
            'in_stock' => (bool) ($product->in_stock ?? false),
            'sku' => $product->sku ?? '',
            'status' => $product->status ?? 'draft',
            'product_type' => $product->product_type ?? 'simple',
            'language' => $product->language ?? 'ru',
            
            'shop' => $product->shop ? [
                'id' => $product->shop->id,
                'name' => $product->shop->name,
                'slug' => $product->shop->slug,
            ] : null,
            
            'categories' => $product->categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ];
            })->toArray(),
            
            'tags' => $product->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ];
            })->toArray(),
            
            'author' => $product->author ? [
                'id' => $product->author->id,
                'name' => $product->author->name,
            ] : null,
            
            'manufacturer' => $product->manufacturer ? [
                'id' => $product->manufacturer->id,
                'name' => $product->manufacturer->name,
            ] : null,
            
            'ratings' => (float) ($product->ratings ?? 0),
            'total_reviews' => (int) ($product->total_reviews ?? 0),
            
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Display summary
     */
    protected function displaySummary(int $indexed, int $errors, string $indexName): void
    {
        $this->newLine();
        $this->info('✅ Indexing completed!');
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Indexed', $indexed],
                ['Errors', $errors],
                ['Success Rate', round(($indexed / max($indexed + $errors, 1)) * 100, 2) . '%'],
            ]
        );

        // Index stats
        $stats = $this->elasticsearch->getIndexStats($indexName);
        if ($stats && isset($stats['indices'][$indexName])) {
            $indexStats = $stats['indices'][$indexName]['total'];
            
            $this->newLine();
            $this->info('📊 Index Statistics:');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Documents', $indexStats['docs']['count'] ?? 0],
                    ['Size', $this->formatBytes($indexStats['store']['size_in_bytes'] ?? 0)],
                ]
            );
        }

        $this->newLine();
        $this->info('🎉 Products are now searchable!');
        $this->line('Test search: <fg=green>curl "https://api.sancan.ru/search?q=test&type=products"</>');
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}


