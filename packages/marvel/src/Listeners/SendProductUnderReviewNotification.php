<?php

namespace Marvel\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Marvel\Events\ProductUnderReview;
use Marvel\Notifications\ProductUnderReviewNotification;

class SendProductUnderReviewNotification implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param ProductUnderReview $event
     * @return void
     */
    public function handle(ProductUnderReview $event)
    {
        $product = $event->product;
        
        // Отправка уведомления админу
        $admins = $this->getAdminUsers();
        foreach ($admins as $admin) {
            $admin->notify(new ProductUnderReviewNotification($product));
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
