<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Оплата заказа</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2>Спасибо за оплату!</h2>
    <p>Ваш заказ <strong>#{{ $orderId }}</strong> успешно оплачен на сумму <strong>{{ number_format($amount, 2, '.', ' ') }} {{ $currency }}</strong>.</p>

    @if (!empty($items))
        <h3>Состав заказа:</h3>
        <ul>
            @foreach ($items as $item)
                <li>{{ $item['name'] ?? 'Товар' }} — {{ $item['quantity'] ?? 1 }} шт. ({{ $item['price'] ?? 0 }} {{ $currency }})</li>
            @endforeach
        </ul>
    @endif

    <p>Мы приступаем к сборке и передаче заказа в службу доставки.</p>
</body>
</html>
