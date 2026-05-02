<?php

namespace Marvel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class ElasticsearchDiagnose extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elasticsearch:diagnose';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose Elasticsearch connection and configuration issues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Elasticsearch Diagnostic Tool');
        $this->info('=================================');
        $this->newLine();

        $issues = [];
        $warnings = [];

        // 1. Check .env configuration
        $this->info('1️⃣  Checking .env configuration...');
        $this->newLine();

        $envVars = [
            'ELASTICSEARCH_ENABLED' => env('ELASTICSEARCH_ENABLED'),
            'ELASTICSEARCH_HOST' => env('ELASTICSEARCH_HOST'),
            'ELASTICSEARCH_PORT' => env('ELASTICSEARCH_PORT'),
            'ELASTICSEARCH_SCHEME' => env('ELASTICSEARCH_SCHEME'),
            'ELASTICSEARCH_USER' => env('ELASTICSEARCH_USER'),
            'ELASTICSEARCH_PASSWORD' => env('ELASTICSEARCH_PASSWORD'),
            'ELASTICSEARCH_INDEX_PREFIX' => env('ELASTICSEARCH_INDEX_PREFIX'),
        ];

        $this->table(
            ['Variable', 'Value', 'Status'],
            array_map(function ($key, $value) use (&$issues) {
                $status = '✅';
                if ($key === 'ELASTICSEARCH_ENABLED' && !$value) {
                    $status = '⚠️';
                    $issues[] = "ELASTICSEARCH_ENABLED is not set or false";
                }
                if ($key === 'ELASTICSEARCH_HOST' && empty($value)) {
                    $status = '❌';
                    $issues[] = "ELASTICSEARCH_HOST is not set";
                }
                if ($key === 'ELASTICSEARCH_PORT' && empty($value)) {
                    $status = '❌';
                    $issues[] = "ELASTICSEARCH_PORT is not set";
                }
                return [
                    $key,
                    $value ? (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value) : '<not set>',
                    $status
                ];
            }, array_keys($envVars), array_values($envVars))
        );
        $this->newLine();

        // 2. Check config file
        $this->info('2️⃣  Checking config/elasticsearch.php...');
        $this->newLine();

        try {
            $config = config('elasticsearch');
            if (!$config) {
                $issues[] = "Config file not loaded";
                $this->error('   ❌ Config file not found or not loaded');
            } else {
                $this->line('   ✅ Config file loaded');
                $this->line('   Enabled: ' . ($config['enabled'] ? 'Yes' : 'No'));
                $this->line('   Hosts: ' . json_encode($config['hosts'] ?? []));
            }
        } catch (\Exception $e) {
            $issues[] = "Error loading config: " . $e->getMessage();
            $this->error('   ❌ Error: ' . $e->getMessage());
        }
        $this->newLine();

        // 3. Check if Elasticsearch service is running (try to connect)
        $this->info('3️⃣  Testing connection to Elasticsearch...');
        $this->newLine();

        $host = env('ELASTICSEARCH_HOST', 'localhost');
        $port = env('ELASTICSEARCH_PORT', 9200);
        $scheme = env('ELASTICSEARCH_SCHEME', 'http');

        // Parse host if it contains port
        if (strpos($host, ':') !== false) {
            [$host, $port] = explode(':', $host, 2);
        }

        $url = "{$scheme}://{$host}:{$port}";

        $this->line("   Testing connection to: {$url}");

        // Try simple HTTP connection
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        
        if (env('ELASTICSEARCH_USER') && env('ELASTICSEARCH_PASSWORD')) {
            curl_setopt($ch, CURLOPT_USERPWD, env('ELASTICSEARCH_USER') . ':' . env('ELASTICSEARCH_PASSWORD'));
        }

        $response = @curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $issues[] = "Connection error: {$curlError}";
            $this->error("   ❌ Connection failed: {$curlError}");
        } elseif ($httpCode === 0) {
            $issues[] = "Cannot connect to Elasticsearch (timeout or service not running)";
            $this->error("   ❌ Cannot connect to Elasticsearch");
            $this->line("   Possible reasons:");
            $this->line("     - Elasticsearch service is not running");
            $this->line("     - Wrong host/port in .env");
            $this->line("     - Firewall blocking connection");
        } elseif ($httpCode >= 200 && $httpCode < 300) {
            $this->info("   ✅ Connection successful! (HTTP {$httpCode})");
            try {
                $data = json_decode($response, true);
                if (isset($data['version']['number'])) {
                    $this->line("   Elasticsearch version: {$data['version']['number']}");
                }
                if (isset($data['cluster_name'])) {
                    $this->line("   Cluster name: {$data['cluster_name']}");
                }
            } catch (\Exception $e) {
                // Ignore JSON parse errors
            }
        } elseif ($httpCode === 401) {
            $issues[] = "Authentication failed (401)";
            $this->error("   ❌ Authentication failed (401)");
            $this->line("   Check ELASTICSEARCH_USER and ELASTICSEARCH_PASSWORD in .env");
        } else {
            $issues[] = "Unexpected HTTP code: {$httpCode}";
            $this->warn("   ⚠️  Unexpected response: HTTP {$httpCode}");
        }
        $this->newLine();

        // 4. Try to use ElasticsearchService
        $this->info('4️⃣  Testing ElasticsearchService...');
        $this->newLine();

        try {
            $service = app(\Marvel\Services\ElasticsearchService::class);
            $this->line('   ✅ ElasticsearchService instantiated');

            $health = $service->getClusterHealth();
            if ($health) {
                $this->info('   ✅ getClusterHealth() works');
                $this->line("   Cluster: {$health['cluster_name']}");
                $this->line("   Status: {$health['status']}");
            } else {
                $issues[] = "getClusterHealth() returned null";
                $this->error('   ❌ getClusterHealth() failed');
            }
        } catch (\Exception $e) {
            $issues[] = "ElasticsearchService error: " . $e->getMessage();
            $this->error('   ❌ Error: ' . $e->getMessage());
            $this->line('   Stack trace:');
            $this->line('   ' . substr($e->getTraceAsString(), 0, 200) . '...');
        }
        $this->newLine();

        // Summary
        $this->info('📊 Diagnostic Summary');
        $this->info('====================');
        $this->newLine();

        if (empty($issues)) {
            $this->info('✅ No issues found! Elasticsearch should be working.');
        } else {
            $this->error('❌ Issues found:');
            foreach ($issues as $issue) {
                $this->line("   • {$issue}");
            }
            $this->newLine();

            $this->info('💡 Recommendations:');
            $this->newLine();

            if (in_array('Cannot connect to Elasticsearch', $issues) || in_array('Connection error', $issues)) {
                $this->line('   1. Check if Elasticsearch is running:');
                $this->line('      sudo systemctl status elasticsearch');
                $this->line('      # or');
                $this->line('      docker ps | grep elasticsearch');
                $this->newLine();
                $this->line('   2. If not running, start it:');
                $this->line('      sudo systemctl start elasticsearch');
                $this->line('      # or');
                $this->line('      docker-compose up -d elasticsearch');
                $this->newLine();
            }

            if (in_array('ELASTICSEARCH_HOST is not set', $issues)) {
                $this->line('   3. Add to .env file:');
                $this->line('      ELASTICSEARCH_HOST=localhost');
                $this->line('      ELASTICSEARCH_PORT=9200');
                $this->newLine();
            }

            if (in_array('Authentication failed', $issues)) {
                $this->line('   4. Check credentials in .env:');
                $this->line('      ELASTICSEARCH_USER=your_user');
                $this->line('      ELASTICSEARCH_PASSWORD=your_password');
                $this->newLine();
            }

            $this->line('   5. After fixing, clear config cache:');
            $this->line('      php artisan config:clear');
            $this->newLine();
        }

        return empty($issues) ? 0 : 1;
    }
}

