<?php

namespace Marvel\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Marvel\Database\Models\Question;

class QuestionCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $question;
    protected $receiver;

    /**
     * Create a new notification instance.
     */
    public function __construct(Question $question, string $receiver = 'admin')
    {
        $this->question = $question;
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
        $product = $this->question->product;
        $url = '';
        
        if ($product) {
            $url = config('shop.shop_url') . '/products/' . $product->slug;
        }

        $subject = $this->receiver === 'admin' 
            ? 'Новый вопрос от клиента'
            : 'Новый вопрос о вашем товаре';

        return (new MailMessage)
            ->subject($subject)
            ->markdown('emails.question.question-created', [
                'question' => $this->question,
                'product' => $product,
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
