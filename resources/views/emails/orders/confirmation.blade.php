@component('mail::message')
# Ваш заказ подтверждён

Спасибо за покупку! Ваш заказ **#{{ $order->id }}** успешно оплачен.

## Приобретённые книги

@foreach ($order->items as $item)
- **{{ $item->book->title }}** — {{ number_format($item->price / 100, 2, ',', ' ') }} ₽
@endforeach

**Итого:** {{ number_format($order->total_amount / 100, 2, ',', ' ') }} ₽

Книги доступны в вашей [библиотеке]({{ url('/cabinet/library') }}).

С уважением,<br>
{{ config('app.name') }}
@endcomponent
