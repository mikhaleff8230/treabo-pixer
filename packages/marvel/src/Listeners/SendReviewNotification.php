<?php

namespace App\Listeners;

use App\Events\ReviewCreated;
use App\Notifications\NewReviewCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Marvel\Database\Models\Shop;
use Marvel\Enums\EventType;
use Marvel\Traits\SmsTrait;

class SendReviewNotification implements ShouldQueue
{
    use SmsTrait;

    /**
     * Handle the event.
     *
     * @param  ReviewCreated  $event
     * @return void
     */
    public function handle(ReviewCreated $event)
    {
        $review = $event->review;
        $emailReceiver = $this->getWhichUserWillGetEmail(EventType::REVIEW_CREATED, $review->language ?? DEFAULT_LANGUAGE);
        
        // Отправка уведомления владельцу магазина
        if ($emailReceiver['vendor']) {
            $shop_id = $review->shop_id;
            $shop = Shop::with('owner')->findOrFail($shop_id);
            $shop_owner = $shop->owner;
            if ($shop_owner) {
                $shop_owner->notify(new NewReviewCreated($review));
            }
        }
        
        // Отправка уведомления админу
        if ($emailReceiver['admin']) {
            $admins = $this->adminList();
            foreach ($admins as $admin) {
                $admin->notify(new NewReviewCreated($review));
            }
        }
    }
}
