<?php

namespace Marvel\Console\Commands;

use Illuminate\Console\Command;
use Marvel\Jobs\UpdateCdekNumbers;

class UpdateCdekNumbersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cdek:update-numbers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update CDEK numbers for shipments that don\'t have them yet';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting CDEK numbers update...');
        
        // Запускаем задачу обновления
        UpdateCdekNumbers::dispatch();
        
        $this->info('CDEK numbers update job dispatched successfully!');
        
        return 0;
    }
}

