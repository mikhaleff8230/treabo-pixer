<?php

namespace Marvel\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Marvel\Events\ShopCreated;
use Marvel\Notifications\ShopCreatedNotification;

class SendShopCreatedNotification implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param ShopCreated $event
     * @return void
     */
    public function handle(ShopCreated $event)
    {
        $shop = $event->shop;
        
        // Отправка уведомления админу
        $admins = $this->getAdminUsers();
        foreach ($admins as $admin) {
            $admin->notify(new ShopCreatedNotification($shop, 'admin'));
        }
        
        // Отправка уведомления владельцу магазина
        if ($shop->owner) {
            $shop->owner->notify(new ShopCreatedNotification($shop, 'store_owner'));
        }
    }
    
    /**
     * Get admin users
     */
    private function getAdminUsers()
    {
        return \Marvel\Database\Models\User::whereHas('permissions', function($query) {
            $query->where('name', \Marvel\Enums\Permission::SUPER_ADMIN);
        })->get();
    }
}
