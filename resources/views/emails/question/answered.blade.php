{{--$question collection is available here--}}

@component('mail::message')
# В: {{$question->question}}?

О: {{$question->answer}}

@component('mail::button', ['url' => $url ])
    Посмотреть {{$product->name}}
@endcomponent
С уважением,<br>
Sancan.ru
@endcomponent
