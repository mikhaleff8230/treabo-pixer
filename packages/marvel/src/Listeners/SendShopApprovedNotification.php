<?php

namespace Marvel\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Marvel\Events\ShopApproved;
use Marvel\Notifications\ShopApprovedNotification;

class SendShopApprovedNotification implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param ShopApproved $event
     * @return void
     */
    public function handle(ShopApproved $event)
    {
        $shop = $event->shop;
        
        // Отправка уведомления владельцу магазина
        if ($shop->owner) {
            $shop->owner->notify(new ShopApprovedNotification($shop));
        }
    }
}
