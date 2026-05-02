@component('mail::message')
# {{ $receiver === 'admin' ? 'Новый магазин зарегистрирован' : 'Ваш магазин успешно зарегистрирован' }}

@if($receiver === 'admin')
**Информация о магазине:**
- Название: {{ $shop->name }}
- Slug: {{ $shop->slug }}
- Владелец: {{ $shop->owner ? $shop->owner->name : 'N/A' }}
- Email владельца: {{ $shop->owner ? $shop->owner->email : 'N/A' }}
- Статус: {{ $shop->is_active ? 'Активен' : 'Неактивен' }}

**Описание:**
{{ $shop->description }}

**Адрес:**
{{ $shop->address ?? 'Не указан' }}
@else
Поздравляем! Ваш магазин **"{{ $shop->name }}"** успешно зарегистрирован.

**Данные вашего магазина:**
- Название: {{ $shop->name }}
- URL: {{ config('shop.shop_url') }}/{{ $shop->slug }}
- Статус: {{ $shop->is_active ? 'Активен' : 'Ожидает модерации' }}

**Что дальше?**
- Загружайте товары
- Настройте оплату
- Принимайте заказы
@endif

@component('mail::button', ['url' => $url])
{{ $receiver === 'admin' ? 'Управление магазином' : 'Перейти в магазин' }}
@endcomponent

С уважением,<br>
Sancan.ru
@endcomponent
