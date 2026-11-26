@extends('emails.layouts.base', ['subject' => 'Отмена заказа'])

@section('content')
    <h2 style="color: #dc2626;">Заказ #{{ $orderId }} отменён</h2>
    <p>К сожалению, ваш заказ был отменён.</p>
    <p><strong>Причина отмены:</strong> {{ $reason }}.</p>

    @if ($refundedAmount !== null && $refundedAmount > 0 && $currency !== null)
        <p>
            Сумма возврата:
            <strong>{{ \Illuminate\Support\Number::currency($refundedAmount, in: $currency, locale: 'ru') }}</strong>.
        </p>
    @endif

    <p>Если с вашего счёта были списаны средства, возврат поступит в течение нескольких рабочих дней.</p>
@endsection
