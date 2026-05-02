<?php

namespace Marvel\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Marvel\Enums\EventType;
use Marvel\Events\QuestionCreated;
use Marvel\Notifications\QuestionCreatedNotification;
use Marvel\Traits\SmsTrait;

class SendQuestionCreatedNotification implements ShouldQueue
{
    use SmsTrait;

    /**
     * Handle the event.
     *
     * @param QuestionCreated $event
     * @return void
     */
    public function handle(QuestionCreated $event)
    {
        $question = $event->question;
        $emailReceiver = $this->getWhichUserWillGetEmail(EventType::QUESTION_CREATED, $question->language ?? DEFAULT_LANGUAGE);
        
        // Отправка уведомления админу
        if ($emailReceiver['admin']) {
            $admins = $this->adminList();
            foreach ($admins as $admin) {
                $admin->notify(new QuestionCreatedNotification($question, 'admin'));
            }
        }
        
        // Отправка уведомления владельцу магазина
        if ($emailReceiver['vendor'] && $question->shop && $question->shop->owner) {
            $question->shop->owner->notify(new QuestionCreatedNotification($question, 'store_owner'));
        }
    }
}
