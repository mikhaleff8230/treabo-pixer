<?php

namespace Marvel\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Marvel\Database\Models\Shop;

class ShopApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $shop;

    /**
     * Create a new notification instance.
     */
    public function __construct(Shop $shop)
    {
        $this->shop = $shop;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        $url = config('shop.dashboard_url') . '/' . $this->shop->slug;

        return (new MailMessage)
            ->subject('Ваш магазин одобрен!')
            ->markdown('emails.shop.shop-approved', [
                'shop' => $this->shop,
                'url' => $url
            ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
