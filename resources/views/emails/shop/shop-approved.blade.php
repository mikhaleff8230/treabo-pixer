@component('mail::message')
# Ваш магазин одобрен!

Поздравляем, {{ $shop->owner ? $shop->owner->name : 'Владелец' }}!

Ваш магазин **"{{ $shop->name }}"** успешно прошел модерацию и активирован.

**Информация о магазине:**
- Название: {{ $shop->name }}
- URL: {{ config('shop.shop_url') }}/{{ $shop->slug }}
- Статус: Активен

**Что дальше?**
- Добавляйте товары
- Настраивайте магазин
- Принимайте заказы
- Начинайте зарабатывать

@component('mail::button', ['url' => $url])
Перейти в магазин
@endcomponent

С уважением,<br>
Sancan.ru
@endcomponent
