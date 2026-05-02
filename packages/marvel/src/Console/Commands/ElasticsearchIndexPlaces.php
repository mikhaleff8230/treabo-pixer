<?php

namespace Marvel\Console\Commands;

use Marvel\Services\ElasticsearchService;
use Marvel\Database\Models\Place;
use Illuminate\Console\Command;

class ElasticsearchIndexPlaces extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elasticsearch:index-places 
                            {--fresh : Delete and recreate index before indexing}
                            {--chunk=500 : Number of places to index at once}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Index places into Elasticsearch';

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
        $this->info('🔍 Starting places indexing...');
        $this->newLine();

        $indexName = config('elasticsearch.indices.places.name');
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
                $this->elasticsearch->createIndex('places');
                $this->newLine();
            }
        }

        // Get total places count
        $query = Place::query()
            ->with(['user', 'hashtags', 'products', 'images', 'videos', 'likes', 'followers']);

        $total = $query->count();

        if ($total === 0) {
            $this->warn('No places found to index.');
            return 0;
        }

        $this->info("📊 Total places to index: {$total}");
        $this->newLine();

        // Progress bar
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');
        $bar->setMessage('Starting...');
        $bar->start();

        $indexed = 0;
        $errors = 0;

        // Process in chunks
        $query->chunk($chunkSize, function ($places) use ($indexName, $bar, &$indexed, &$errors) {
            $batch = [];

            foreach ($places as $place) {
                try {
                    $batch[] = $this->transformPlace($place);
                    $indexed++;
                } catch (\Exception $e) {
                    $errors++;
                    \Log::error('Failed to transform place', [
                        'place_id' => $place->id,
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
     * Transform place model to Elasticsearch document
     */
    protected function transformPlace(Place $place): array
    {
        return [
            'id' => $place->id,
            'title' => $place->title ?? '',
            'description' => strip_tags($place->description ?? ''),
            
            'user' => $place->user ? [
                'id' => $place->user->id,
                'name' => $place->user->name,
            ] : null,
            
            'hashtags' => $place->hashtags->map(function ($hashtag) {
                return [
                    'id' => $hashtag->id,
                    'name' => $hashtag->name,
                ];
            })->toArray(),
            
            'products' => $place->products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                ];
            })->toArray(),
            
            'images_count' => $place->images->count(),
            'videos_count' => $place->videos->count(),
            'likes_count' => $place->likes->count(),
            'followers_count' => $place->followers->count(),
            
            'created_at' => $place->created_at?->toIso8601String(),
            'updated_at' => $place->updated_at?->toIso8601String(),
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
        $this->info('🎉 Places are now searchable!');
        $this->line('Test search: <fg=green>curl "https://api.sancan.ru/search?q=test&type=places"</>');
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


