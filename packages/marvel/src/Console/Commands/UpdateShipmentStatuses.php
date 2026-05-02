<?php

namespace Marvel\Console\Commands;

use Illuminate\Console\Command;
use Marvel\Services\ShipmentService;

class UpdateShipmentStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shipments:update-status {--limit=100 : Maximum number of shipments to update}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update status of active shipments from delivery services';

    private ShipmentService $shipmentService;

    /**
     * Create a new command instance.
     */
    public function __construct(ShipmentService $shipmentService)
    {
        parent::__construct();
        $this->shipmentService = $shipmentService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting shipment status update...');

        try {
            $updated = $this->shipmentService->updateActiveShipmentsStatus();
            
            $this->info("Successfully updated status for {$updated} shipments.");
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to update shipment statuses: ' . $e->getMessage());
            
            return Command::FAILURE;
        }
    }
}
