@component('mail::message')
# Ответ дан клиенту

Вы дали ответ на вопрос клиента о вашем товаре **{{ $product ? $product->name : 'N/A' }}**.

**Вопрос клиента:**
{{ $question->question }}

**Ваш ответ:**
{{ $question->answer }}

**Информация о клиенте:**
- Имя: {{ $question->customer ? $question->customer->name : 'Гость' }}
- Email: {{ $question->customer ? $question->customer->email : 'N/A' }}

@component('mail::button', ['url' => $url])
Посмотреть товар
@endcomponent

С уважением,<br>
Sancan.ru
@endcomponent
