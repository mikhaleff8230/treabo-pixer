<?php

namespace Marvel\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Marvel\Events\ProductCreated;
use Marvel\Notifications\ProductCreatedNotification;

class SendProductCreatedNotification implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param ProductCreated $event
     * @return void
     */
    public function handle(ProductCreated $event)
    {
        $product = $event->product;
        
        // Отправка уведомления админу
        $admins = $this->getAdminUsers();
        foreach ($admins as $admin) {
            $admin->notify(new ProductCreatedNotification($product, 'admin'));
        }
        
        // Отправка уведомления владельцу магазина
        if ($product->shop && $product->shop->owner) {
            $product->shop->owner->notify(new ProductCreatedNotification($product, 'store_owner'));
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
