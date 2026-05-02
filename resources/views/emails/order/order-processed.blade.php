{{--$order collection is available here--}}

@component('mail::message')
# Ваш заказ обрабатывается!

Ваш заказ успешно получен и находится в процессе обработки.
Номер отслеживания заказа: {{$order->tracking_number}}

@component('mail::button', ['url' => $url ])
Посмотреть заказ
@endcomponent

С уважением,<br>
Sancan.ru
@endcomponent