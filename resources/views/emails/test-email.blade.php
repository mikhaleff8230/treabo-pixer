@component('mail::message')
# Тестовая конфигурация электронной почты

Это тестовое письмо для проверки правильности настройки электронной почты.

**Детали конфигурации:**
- От: {{ config('mail.from.name') }} <{{ config('mail.from.address') }}>
- Почтовый драйвер: {{ config('mail.default') }}
- Email администратора: {{ config('shop.admin_email') }}

Если вы получили это письмо, ваша конфигурация электронной почты работает правильно!

@component('mail::button', ['url' => config('app.frontend_url')])
Посетить магазин
@endcomponent

С уважением,<br>
Sancan.ru
@endcomponent


