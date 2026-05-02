{{--$participant collection is available here--}}

@component('mail::message')
# Напоминание о сообщении
Сообщение: {{ $participant->message->body }}<br>
@component('mail::button', ['url' => $url ])
    Посмотреть сообщение
@endcomponent
С уважением,<br>
Sancan.ru
@endcomponent
