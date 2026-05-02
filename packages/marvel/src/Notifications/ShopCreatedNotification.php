<?php

namespace Marvel\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Marvel\Database\Models\Shop;

class ShopCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $shop;
    protected $receiver;

    /**
     * Create a new notification instance.
     */
    public function __construct(Shop $shop, string $receiver = 'admin')
    {
        $this->shop = $shop;
        $this->receiver = $receiver;
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
        $subject = $this->receiver === 'admin' 
            ? 'Новый магазин зарегистрирован'
            : 'Ваш магазин успешно зарегистрирован';

        $url = $this->receiver === 'admin'
            ? config('shop.dashboard_url') . '/shops/' . $this->shop->id
            : config('shop.dashboard_url') . '/' . $this->shop->slug;

        return (new MailMessage)
            ->subject($subject)
            ->markdown('emails.shop.shop-created', [
                'shop' => $this->shop,
                'receiver' => $this->receiver,
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
