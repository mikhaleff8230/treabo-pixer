<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateYandexFeed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:products {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export products to an XML file';

    // URL сайта

public $SITE_URL = 'https://treabo.md/';
// Название сайта

public $SITE_NAME = 'Treabo';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        //
    }
}
