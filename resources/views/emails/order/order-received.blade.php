{{--$order collection is available here--}}

@component('mail::message')
@if($receiver == 'admin')
    # {{ __('sms.order.orderCreated.admin.subject') }}

    {{ __('sms.order.orderCreated.admin.message',['ORDER_TRACKING_NUMBER'=>$order->tracking_number,'customer_name'=>$customer]) }}
    
    **Детали заказа:**
    - Номер заказа: #{{ $order->tracking_number }}
    - Клиент: {{ $customer }}
    - Общая сумма: {{ number_format($order->total, 2) }} {{ $order->currency }}
    - Дата заказа: {{ $order->created_at->format('d.m.Y H:i') }}
    - Магазин: {{ $order->shop ? $order->shop->name : 'N/A' }}
@else
    # {{ __('sms.order.orderCreated.storeOwner.subject') }}

    {{ __('sms.order.orderCreated.storeOwner.message',['ORDER_TRACKING_NUMBER'=>$order->tracking_number,'customer_name'=>$customer]) }}
    
    **Детали заказа:**
    - Номер заказа: #{{ $order->tracking_number }}
    - Клиент: {{ $customer }}
    - Общая сумма: {{ number_format($order->total, 2) }} {{ $order->currency }}
    - Дата заказа: {{ $order->created_at->format('d.m.Y H:i') }}
@endif

@if($order->billing_address)
**Адрес для выставления счета:**
{{ $order->billing_address->street_address }}
{{ $order->billing_address->city }}, {{ $order->billing_address->state }} {{ $order->billing_address->zip }}
{{ $order->billing_address->country }}
@endif

@component('mail::button', ['url' => $url ])
    {{__('common.view-order')}}
@endcomponent

{{__('common.thanks')}},<br>
Sancan.ru
@endcomponent