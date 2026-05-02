<?php

namespace Marvel\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Marvel\Database\Models\User;

class UserRegisteredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $user;
    protected $receiver;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $user, string $receiver = 'admin')
    {
        $this->user = $user;
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
        if ($this->receiver === 'admin') {
            $subject = 'Новый пользователь зарегистрирован';
        } else {
            $subject = 'Добро пожаловать!';
        }

        $url = $this->receiver === 'admin'
            ? config('shop.dashboard_url') . '/users/' . $this->user->id
            : config('shop.shop_url');

        return (new MailMessage)
            ->subject($subject)
            ->markdown('emails.user.user-registered', [
                'user' => $this->user,
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
