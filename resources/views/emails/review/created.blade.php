{{--$review collection is available here--}}

@component('mail::message')
# Оставлен новый отзыв

Комментарий: {{ $review->comment }}<br>
Оценка: {{ $review->rating }}

@component('mail::button', ['url' => $url ])
    Посмотреть {{$product->name}}
@endcomponent
С уважением,<br>
Sancan.ru
@endcomponent
