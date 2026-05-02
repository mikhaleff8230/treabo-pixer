<?php

namespace App\Listeners;

use App\Events\QuestionAnswered;
use App\Models\User;
use App\Notifications\NotifyQuestionAnswered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Marvel\Enums\EventType;
use Marvel\Traits\SmsTrait;

class SendQuestionAnsweredNotification implements ShouldQueue
{
    use SmsTrait;
    /**
     * Handle the event.
     *
     * @param  QuestionAnswered  $event
     * @return void
     */
    public function handle(QuestionAnswered $event)
    {
        $question = $event->question;
        $emailReceiver = $this->getWhichUserWillGetEmail(EventType::QUESTION_ANSWERED, $question->language ?? DEFAULT_LANGUAGE);
        
        // Отправка уведомления клиенту
        if ($emailReceiver['customer'] && $question->customer) {
            $customer = User::findOrFail($question->user_id);
            $customer->notify(new NotifyQuestionAnswered($question));
        }
        
        // Отправка уведомления владельцу магазина о новом ответе
        if ($question->shop && $question->shop->owner) {
            $question->shop->owner->notify(new \Marvel\Notifications\QuestionAnsweredNotification($question));
        }
    }
}
