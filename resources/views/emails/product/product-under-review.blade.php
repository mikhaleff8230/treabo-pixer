@component('mail::message')
# Новый товар на модерации

**Информация о товаре:**
- Название: {{ $product->name }}
- SKU: {{ $product->sku ?? 'N/A' }}
- Цена: {{ number_format($product->price, 2) }} {{ $product->unit }}
- Магазин: {{ $product->shop ? $product->shop->name : 'N/A' }}
- Владелец: {{ $product->shop && $product->shop->owner ? $product->shop->owner->name : 'N/A' }}
- Email владельца: {{ $product->shop && $product->shop->owner ? $product->shop->owner->email : 'N/A' }}

**Описание:**
{{ Str::limit($product->description ?? '', 200) }}

@component('mail::button', ['url' => $url])
Просмотреть товар
@endcomponent

С уважением,<br>
Sancan.ru
@endcomponent
