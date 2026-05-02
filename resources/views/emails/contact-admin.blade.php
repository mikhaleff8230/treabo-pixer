@component('mail::message')
# {{$details['subject']}}

Email: {{$details['email']}}

{{$details['description']}}

С уважением,<br>
{{ $details['name'] }}
@endcomponent