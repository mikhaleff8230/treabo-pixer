<?php

namespace Marvel\Console\Commands;

use Marvel\Services\ElasticsearchService;
use Illuminate\Console\Command;

class ElasticsearchStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elasticsearch:status {--detailed : Show detailed statistics}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show Elasticsearch cluster and indices status';

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
        $this->info('🔍 Elasticsearch Status Report');
        $this->newLine();

        // Cluster health
        $this->displayClusterHealth();
        $this->newLine();

        // Indices status
        $this->displayIndicesStatus();

        return 0;
    }

    /**
     * Display cluster health
     */
    protected function displayClusterHealth(): void
    {
        $this->info('🏥 Cluster Health:');
        
        $health = $this->elasticsearch->getClusterHealth();
        
        if (!$health) {
            $this->error('  ❌ Cannot connect to Elasticsearch!');
            return;
        }

        $headers = ['Metric', 'Value'];
        $data = [
            ['Cluster Name', $health['cluster_name']],
            ['Status', $this->getStatusIcon($health['status']) . ' ' . $health['status']],
            ['Nodes', $health['number_of_nodes']],
            ['Data Nodes', $health['number_of_data_nodes']],
            ['Active Shards', $health['active_shards']],
            ['Relocating Shards', $health['relocating_shards']],
            ['Initializing Shards', $health['initializing_shards']],
            ['Unassigned Shards', $health['unassigned_shards']],
        ];

        $this->table($headers, $data);
    }

    /**
     * Display indices status
     */
    protected function displayIndicesStatus(): void
    {
        $this->info('📊 Indices Status:');
        
        $indices = config('elasticsearch.indices');
        $headers = ['Index', 'Exists', 'Documents', 'Size', 'Status'];
        $data = [];

        foreach ($indices as $indexType => $config) {
            $indexName = $config['name'];
            $exists = $this->elasticsearch->indexExists($indexName);

            if ($exists) {
                $stats = $this->elasticsearch->getIndexStats($indexName);
                $indexStats = $stats['indices'][$indexName]['total'] ?? [];
                
                $docCount = $indexStats['docs']['count'] ?? 0;
                $size = $this->formatBytes($indexStats['store']['size_in_bytes'] ?? 0);
                $status = '✅ Active';
            } else {
                $docCount = '-';
                $size = '-';
                $status = '❌ Not created';
            }

            $data[] = [
                $indexName,
                $exists ? '✅ Yes' : '❌ No',
                $docCount,
                $size,
                $status,
            ];
        }

        $this->table($headers, $data);

        // Detailed stats
        if ($this->option('detailed')) {
            $this->newLine();
            $this->displayDetailedStats();
        }
    }

    /**
     * Display detailed statistics
     */
    protected function displayDetailedStats(): void
    {
        $this->info('📈 Detailed Statistics:');
        $this->newLine();

        $indices = config('elasticsearch.indices');

        foreach ($indices as $indexType => $config) {
            $indexName = $config['name'];
            
            if (!$this->elasticsearch->indexExists($indexName)) {
                continue;
            }

            $this->line("  <fg=cyan>{$indexName}</>:");
            
            $stats = $this->elasticsearch->getIndexStats($indexName);
            $indexStats = $stats['indices'][$indexName]['total'] ?? [];

            // Documents
            $this->line("    Documents:");
            $this->line("      Count: " . ($indexStats['docs']['count'] ?? 0));
            $this->line("      Deleted: " . ($indexStats['docs']['deleted'] ?? 0));

            // Store
            $this->line("    Storage:");
            $this->line("      Size: " . $this->formatBytes($indexStats['store']['size_in_bytes'] ?? 0));

            // Search
            if (isset($indexStats['search'])) {
                $this->line("    Search:");
                $this->line("      Query Total: " . $indexStats['search']['query_total']);
                $this->line("      Query Time: " . ($indexStats['search']['query_time_in_millis'] ?? 0) . 'ms');
                $this->line("      Fetch Total: " . $indexStats['search']['fetch_total']);
            }

            // Indexing
            if (isset($indexStats['indexing'])) {
                $this->line("    Indexing:");
                $this->line("      Index Total: " . $indexStats['indexing']['index_total']);
                $this->line("      Index Time: " . ($indexStats['indexing']['index_time_in_millis'] ?? 0) . 'ms');
            }

            $this->newLine();
        }
    }

    /**
     * Get status icon
     */
    protected function getStatusIcon(string $status): string
    {
        return match($status) {
            'green' => '🟢',
            'yellow' => '🟡',
            'red' => '🔴',
            default => '⚪',
        };
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


