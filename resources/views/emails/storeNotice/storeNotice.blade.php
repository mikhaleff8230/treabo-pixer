{{--$notice collection is available here--}}

@component('mail::message')
# Заголовок:
{{$notice->notice}}
# Описание:
{{$notice->description ?? ''}}
<br>
@if ($action == 'create' )
Уведомление создано пользователем {{$notice->creator->name ?? ''}}
@elseif($action == 'update')
Уведомление обновлено пользователем {{$notice->creator->name ?? ''}}
@else
Уведомление удалено пользователем {{$notice->creator->name ?? ''}}
@endif
Действительно до {{ date('H:i:s',strtotime($notice->expired_at)) }} {{ date('d F, Y',strtotime($notice->expired_at)) }}

С уважением,<br>
Sancan.ru
@endcomponent
