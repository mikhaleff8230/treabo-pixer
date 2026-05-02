<?php

namespace Marvel\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Marvel\Database\Models\Shop;
use Marvel\Database\Models\User;

class StaffAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $shop;
    protected $staff;
    protected $addedBy;

    /**
     * Create a new notification instance.
     */
    public function __construct(Shop $shop, User $staff, User $addedBy)
    {
        $this->shop = $shop;
        $this->staff = $staff;
        $this->addedBy = $addedBy;
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
        $url = config('shop.dashboard_url') . '/' . $this->shop->slug . '/staffs';

        return (new MailMessage)
            ->subject('Новый сотрудник добавлен в ваш магазин')
            ->markdown('emails.staff.staff-added', [
                'shop' => $this->shop,
                'staff' => $this->staff,
                'addedBy' => $this->addedBy,
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
