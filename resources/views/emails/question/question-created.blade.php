@component('mail::message')
# {{ $receiver === 'admin' ? 'Новый вопрос от клиента' : 'Новый вопрос о вашем товаре' }}

@if($receiver === 'admin')
**Информация о вопросе:**
- Клиент: {{ $question->customer ? $question->customer->name : 'Гость' }}
- Email: {{ $question->customer ? $question->customer->email : 'N/A' }}
- Товар: {{ $product ? $product->name : 'N/A' }}
- Магазин: {{ $question->shop ? $question->shop->name : 'N/A' }}

**Вопрос клиента:**
{{ $question->question }}
@else
**Вопрос клиента о вашем товаре "{{ $product ? $product->name : 'N/A' }}":**

{{ $question->question }}

**Информация о клиенте:**
- Имя: {{ $question->customer ? $question->customer->name : 'Гость' }}
- Email: {{ $question->customer ? $question->customer->email : 'N/A' }}
@endif

@component('mail::button', ['url' => $url])
{{ $product ? 'Посмотреть товар' : 'Открыть магазин' }}
@endcomponent

С уважением,<br>
Sancan.ru
@endcomponent
