<?php

namespace Marvel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Marvel\Services\CdekService;
use Marvel\Database\Models\Shipment;

class UpdateCdekNumbers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(CdekService $cdekService): void
    {
        try {
            // Находим все отправления CDEK без cdek_number
            $shipments = Shipment::where('service', Shipment::SERVICE_SDEK)
                ->whereNotNull('external_id')
                ->whereNull('cdek_number')
                ->get();

            Log::info('UpdateCdekNumbers: Processing shipments', [
                'count' => $shipments->count()
            ]);

            foreach ($shipments as $shipment) {
                try {
                    // Получаем cdek_number от CDEK
                    $cdekNumber = $cdekService->getCdekNumber($shipment->external_id);
                    
                    if ($cdekNumber) {
                        // Обновляем отправление
                        $shipment->update([
                            'cdek_number' => $cdekNumber,
                            'status' => Shipment::STATUS_PROCESSED
                        ]);

                        Log::info('UpdateCdekNumbers: Updated shipment with cdek_number', [
                            'shipment_id' => $shipment->id,
                            'external_id' => $shipment->external_id,
                            'cdek_number' => $cdekNumber
                        ]);
                    } else {
                        Log::info('UpdateCdekNumbers: cdek_number not yet available', [
                            'shipment_id' => $shipment->id,
                            'external_id' => $shipment->external_id
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('UpdateCdekNumbers: Failed to update shipment', [
                        'shipment_id' => $shipment->id,
                        'external_id' => $shipment->external_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('UpdateCdekNumbers: Job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}

