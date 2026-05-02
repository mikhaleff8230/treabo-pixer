<?php

namespace Marvel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Marvel\Services\XmlImportService;

class XmlImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $content;
    protected string $ext;
    protected array $options;
    protected array $fieldMapping;
    protected string $token;

    public function __construct(string $content, string $ext, array $options, array $fieldMapping, string $token)
    {
        $this->content = $content;
        $this->ext = $ext;
        $this->options = $options;
        $this->fieldMapping = $fieldMapping;
        $this->token = $token;
    }

    public function handle(): void
    {
        Log::info('XmlImportJob started', [
            'token' => $this->token,
            'ext' => $this->ext,
            'content_size' => strlen($this->content),
            'has_field_mapping' => !empty($this->fieldMapping)
        ]);

        $service = new XmlImportService();
        if (!empty($this->fieldMapping)) {
            $service->setCustomFieldMapping($this->fieldMapping);
        }

        try {
            if (in_array($this->ext, ['csv'])) {
                $result = $service->importFromCsv($this->content, $this->options, $this->fieldMapping);
            } else {
                $result = $service->importFromXml($this->content, $this->options);
            }
            Log::info('XmlImportJob completed successfully', [
                'token' => $this->token,
                'result' => $result
            ]);
        } catch (\Throwable $e) {
            Log::error('XmlImportJob failed', [
                'token' => $this->token,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            $result = [
                'total' => 0,
                'imported' => 0,
                'updated' => 0,
                'errors' => 1,
                'errors_list' => ['Job failed: ' . $e->getMessage()],
            ];
        }

        // Persist stats to storage for polling
        $dir = storage_path('app/xml-import-stats');
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        $path = $dir . '/' . $this->token . '.json';
        file_put_contents($path, json_encode($result));
        
        Log::info('XmlImportJob stats saved', [
            'token' => $this->token,
            'path' => $path,
            'exists' => file_exists($path)
        ]);
    }
}


