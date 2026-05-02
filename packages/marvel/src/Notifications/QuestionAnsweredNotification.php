<?php

namespace Marvel\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;
use Marvel\Database\Models\Question;

class QuestionAnsweredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $question;

    /**
     * Create a new notification instance.
     */
    public function __construct(Question $question)
    {
        $this->question = $question;
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
        App::setLocale($this->question->language ?? DEFAULT_LANGUAGE);
        
        $product = $this->question->product;
        $url = '';
        
        if ($product) {
            $url = config('shop.shop_url') . '/products/' . $product->slug;
        }

        return (new MailMessage)
            ->subject('Клиенту дан ответ на его вопрос')
            ->markdown('emails.question.question-answered-owner', [
                'question' => $this->question,
                'product' => $product,
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
