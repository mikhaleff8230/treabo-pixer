@component('mail::message')
# Новый сотрудник добавлен в ваш магазин

**Информация о магазине:**
- Название: {{ $shop->name }}
- Slug: {{ $shop->slug }}

**Информация о новом сотруднике:**
- Имя: {{ $staff->name }}
- Email: {{ $staff->email }}
- Добавил: {{ $addedBy->name }}

**Права доступа:**
Сотрудник получил права: Customer и Staff

@component('mail::button', ['url' => $url])
Управление сотрудниками
@endcomponent

С уважением,<br>
Sancan.ru
@endcomponent
