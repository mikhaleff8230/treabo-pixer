<?php

namespace Marvel\Console\Commands;

use Marvel\Services\ElasticsearchService;
use Illuminate\Console\Command;

class ElasticsearchSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elasticsearch:setup 
                            {--recreate : Delete and recreate all indices}
                            {--index= : Setup specific index only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup Elasticsearch indices and mappings';

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
        $this->info('🔍 Starting Elasticsearch setup...');
        $this->newLine();

        // Check cluster health
        $this->checkClusterHealth();
        $this->newLine();

        $indices = config('elasticsearch.indices');
        $specificIndex = $this->option('index');

        if ($specificIndex) {
            if (!isset($indices[$specificIndex])) {
                $this->error("Index '{$specificIndex}' not found in configuration!");
                return 1;
            }
            $indices = [$specificIndex => $indices[$specificIndex]];
        }

        foreach ($indices as $indexType => $config) {
            $this->setupIndex($indexType, $config);
        }

        $this->newLine();
        $this->info('✅ Elasticsearch setup completed!');
        $this->newLine();

        $this->displayNextSteps();

        return 0;
    }

    /**
     * Setup a single index
     */
    protected function setupIndex(string $indexType, array $config): void
    {
        $indexName = $config['name'];
        $recreate = $this->option('recreate');

        $this->line("📋 Setting up index: <fg=cyan>{$indexName}</> (type: {$indexType})");

        // Check if index exists
        $exists = $this->elasticsearch->indexExists($indexName);

        if ($exists) {
            if ($recreate) {
                if ($this->confirm("  Index '{$indexName}' exists. Delete and recreate?", false)) {
                    $this->warn("  🗑️  Deleting existing index...");
                    $this->elasticsearch->deleteIndex($indexName);
                    $exists = false;
                } else {
                    $this->info("  ℹ️  Skipping index '{$indexName}'");
                    return;
                }
            } else {
                $this->info("  ✓ Index already exists");
                return;
            }
        }

        // Create index
        if (!$exists) {
            $this->info("  ⚙️  Creating index...");
            
            if ($this->elasticsearch->createIndex($indexType)) {
                $this->info("  ✅ Index created successfully");
                
                // Display stats
                $this->displayIndexStats($indexName);
            } else {
                $this->error("  ❌ Failed to create index");
            }
        }
    }

    /**
     * Check Elasticsearch cluster health
     */
    protected function checkClusterHealth(): void
    {
        $this->info('🏥 Checking Elasticsearch cluster health...');
        
        try {
            $health = $this->elasticsearch->getClusterHealth();
            
            if ($health) {
                $status = $health['status'];
                $statusIcon = [
                    'green' => '🟢',
                    'yellow' => '🟡',
                    'red' => '🔴',
                ];
                
                $this->line("  Cluster: <fg=cyan>{$health['cluster_name']}</>");
                $this->line("  Status: " . ($statusIcon[$status] ?? '⚪') . " <fg=cyan>{$status}</>");
                $this->line("  Nodes: <fg=cyan>{$health['number_of_nodes']}</>");
                $this->line("  Active Shards: <fg=cyan>{$health['active_shards']}</>");
                
                if ($status === 'red') {
                    $this->warn('  ⚠️  Cluster health is RED! Some operations may fail.');
                } elseif ($status === 'yellow') {
                    $this->warn('  ⚠️  Cluster health is YELLOW. This is normal for single-node setups.');
                }
            } else {
                $this->error('  ❌ Cannot connect to Elasticsearch cluster!');
                $this->error('  Please check:');
                $this->error('    - Elasticsearch is running: sudo systemctl status elasticsearch');
                $this->error('    - Connection settings in .env file');
                $this->error('    - Firewall settings');
                exit(1);
            }
        } catch (\Exception $e) {
            $this->error('  ❌ Error: ' . $e->getMessage());
            exit(1);
        }
    }

    /**
     * Display index statistics
     */
    protected function displayIndexStats(string $indexName): void
    {
        $stats = $this->elasticsearch->getIndexStats($indexName);
        
        if ($stats && isset($stats['indices'][$indexName])) {
            $indexStats = $stats['indices'][$indexName];
            $total = $indexStats['total'] ?? [];
            
            $this->line("  📊 Stats:");
            $this->line("     Documents: <fg=cyan>" . ($total['docs']['count'] ?? 0) . "</>");
            $this->line("     Size: <fg=cyan>" . $this->formatBytes($total['store']['size_in_bytes'] ?? 0) . "</>");
        }
    }

    /**
     * Display next steps
     */
    protected function displayNextSteps(): void
    {
        $this->info('📝 Next steps:');
        $this->newLine();
        
        $this->line('  1️⃣  Index products:');
        $this->line('     <fg=green>php artisan elasticsearch:index-products</>');
        $this->newLine();
        
        $this->line('  2️⃣  Index places:');
        $this->line('     <fg=green>php artisan elasticsearch:index-places</>');
        $this->newLine();
        
        $this->line('  3️⃣  Check index status:');
        $this->line('     <fg=green>php artisan elasticsearch:status</>');
        $this->newLine();
        
        $this->line('  4️⃣  Test search:');
        $this->line('     <fg=green>curl "https://api.sancan.ru/search?q=test&type=products"</>');
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


