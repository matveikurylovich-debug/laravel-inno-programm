@extends('emails.layouts.base', ['subject' => 'Оплата заказа'])

@section('content')
    <h2>Спасибо за оплату!</h2>
    <p>
        Ваш заказ <strong>#{{ $orderId }}</strong> успешно оплачен на сумму
        <strong>{{ \Illuminate\Support\Number::currency($amount, in: $currency, locale: 'ru') }}</strong>.
    </p>
    <p>Мы приступаем к сборке и передаче заказа в службу доставки.</p>
@endsection
