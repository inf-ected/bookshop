@component('mail::message')
# Подтверждение заказа №{{ $order->id }}

Спасибо за покупку, **{{ $order->user->name }}**!

Ваш заказ успешно оплачен. Книги уже доступны в вашей библиотеке.

---

## Состав заказа

@component('mail::table')
| Книга | Цена |
|:------|-----:|
@foreach ($order->items as $item)
| {{ $item->book->title }} | {{ number_format($item->price / 100, config('shop.currency_decimals'), config('shop.currency_decimal_sep'), ' ') }} {{ config('shop.currency_symbol') }} |
@endforeach
| **Итого** | **{{ number_format($order->total_amount / 100, 0, ',', ' ') }} {{ config('shop.currency_symbol') }}** |
@endcomponent

@component('mail::button', ['url' => url('/cabinet/library')])
Перейти в библиотеку
@endcomponent

С уважением,<br>
{{ config('app.name') }}
@endcomponent
