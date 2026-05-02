<?php

namespace Marvel\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Marvel\Events\StaffAdded;
use Marvel\Notifications\StaffAddedNotification;

class SendStaffAddedNotification implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param StaffAdded $event
     * @return void
     */
    public function handle(StaffAdded $event)
    {
        $shop = $event->shop;
        $staff = $event->staff;
        $addedBy = $event->addedBy;
        
        // Отправка уведомления владельцу магазина (если добавил не он)
        if ($shop->owner && $shop->owner->id != $addedBy->id) {
            $shop->owner->notify(new StaffAddedNotification($shop, $staff, $addedBy));
        }
        
        // Также отправляем владельцу, если добавил он сам (для информативности)
        if ($shop->owner && $shop->owner->id == $addedBy->id) {
            $shop->owner->notify(new StaffAddedNotification($shop, $staff, $addedBy));
        }
    }
}
