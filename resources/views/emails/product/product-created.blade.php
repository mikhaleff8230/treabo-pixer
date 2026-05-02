@component('mail::message')
# {{ $receiver === 'admin' ? 'Новый товар добавлен' : 'Ваш товар успешно добавлен' }}

@if($receiver === 'admin')
**Информация о товаре:**
- Название: {{ $product->name }}
- SKU: {{ $product->sku ?? 'N/A' }}
- Цена: {{ number_format($product->price, 2) }} {{ $product->unit }}
- Статус: {{ ucfirst($product->status) }}
- Магазин: {{ $product->shop ? $product->shop->name : 'N/A' }}
- Владелец: {{ $product->shop && $product->shop->owner ? $product->shop->owner->name : 'N/A' }}

**Описание:**
{{ Str::limit($product->description ?? '', 200) }}
@else
Поздравляем! Ваш товар **"{{ $product->name }}"** успешно добавлен.

**Информация о товаре:**
- Название: {{ $product->name }}
- SKU: {{ $product->sku ?? 'N/A' }}
- Цена: {{ number_format($product->price, 2) }} {{ $product->unit }}
- Статус: {{ ucfirst($product->status) }}
- Количество: {{ $product->quantity ?? 'Безлимит' }}

**Что дальше?**
- Товар будет проверен администратором
- После одобрения появится в каталоге
- Начните принимать заказы
@endif

@component('mail::button', ['url' => $url])
{{ $receiver === 'admin' ? 'Просмотреть товар' : 'Управлять товаром' }}
@endcomponent

С уважением,<br>
SANCAN.RU
@endcomponent
