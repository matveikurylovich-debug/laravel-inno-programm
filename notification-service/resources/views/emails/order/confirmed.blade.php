@extends('emails.layouts.base', ['subject' => 'Подтверждение заказа'])

@section('content')
    <h2>Ваш заказ подтверждён!</h2>
    <p>Заказ <strong>#{{ $orderId }}</strong> успешно зарезервирован и передан на сборку.</p>

    @if ($amount !== null && $currency !== null)
        <p>
            Сумма заказа:
            <strong>{{ \Illuminate\Support\Number::currency($amount, in: $currency, locale: 'ru') }}</strong>.
        </p>
    @endif

    @if ($estimatedDelivery !== null)
        <p>Ориентировочная дата доставки: <strong>{{ $estimatedDelivery }}</strong>.</p>
    @endif

    <p>Мы уведомим вас, когда статус доставки изменится.</p>
@endsection
