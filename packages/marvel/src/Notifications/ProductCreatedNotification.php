<?php

namespace Marvel\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Marvel\Database\Models\Product;

class ProductCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $product;
    protected $receiver;

    /**
     * Create a new notification instance.
     */
    public function __construct(Product $product, string $receiver = 'admin')
    {
        $this->product = $product;
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
            ? 'Новый товар добавлен' 
            : 'Ваш товар успешно добавлен';

        $url = $this->receiver === 'admin'
            ? config('shop.dashboard_url') . '/products/' . $this->product->id
            : config('shop.dashboard_url') . '/' . $this->product->shop->slug . '/products/' . $this->product->id;

        return (new MailMessage)
            ->subject($subject)
            ->markdown('emails.product.product-created', [
                'product' => $this->product,
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
