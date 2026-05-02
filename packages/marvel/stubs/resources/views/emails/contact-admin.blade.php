@component('mail::message')
# {{$details['subject']}}

Email: {{$details['email']}}

{{$details['description']}}

Спасибо,<br>
{{ $details['name'] }}
@endcomponent