{{--$order collection is available here--}}

@component('mail::message')
# Модерация успешна!

Ваш товар одобрен. Теперь вы можете опубликовать или снять с публикации ваш товар!

@component('mail::button', ['url' => $url ])
Посмотреть товар
@endcomponent

С уважением,<br>
Sancan.ru
@endcomponent