{{--$order collection is available here--}}

@component('mail::message')
# Товар отклонен

Ваш товар был отклонен. Проверьте информацию о товаре или свяжитесь с администратором.

@component('mail::button', ['url' => $url ])
Посмотреть товар
@endcomponent

С уважением,<br>
Sancan.ru
@endcomponent