@component('mail::message')
# {{ $subject }}

{{ $message }}

@component('mail::button', ['url' => config('app.frontend_url')])
Посетить магазин
@endcomponent

С уважением,<br>
SANCAN.RU
@endcomponent


