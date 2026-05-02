@component('mail::message')
# {{ $receiver === 'admin' ? 'Новый пользователь зарегистрирован' : 'Добро пожаловать!' }}

@if($receiver === 'admin')
**Информация о новом пользователе:**
- Имя: {{ $user->name }}
- Email: {{ $user->email }}
- ID: {{ $user->id }}
- Дата регистрации: {{ $user->created_at->format('d.m.Y H:i') }}
- Права доступа: {{ ucfirst(str_replace('_', ' ', $user->permissions->pluck('name')->first() ?? 'customer')) }}

@if($user->managed_shop)
**Магазин:**
- Название: {{ $user->managed_shop->name ?? 'N/A' }}
- Slug: {{ $user->managed_shop->slug ?? 'N/A' }}
@endif
@else
# Добро пожаловать, {{ $user->name }}!

Спасибо за регистрацию на Sancan.ru.

**Ваш аккаунт:**
- Email: {{ $user->email }}
- Статус: Активен

**Что дальше?**
- Начните делать покупки
- Добавьте адрес доставки
- Следите за заказами

@if($user->managed_shop)
- Управляйте вашим магазином
@endif
@endif

@component('mail::button', ['url' => $url])
{{ $receiver === 'admin' ? 'Просмотреть пользователя' : 'Начать покупки' }}
@endcomponent

С уважением,<br>
Sancan.ru
@endcomponent
