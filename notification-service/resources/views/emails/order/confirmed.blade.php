<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Подтверждение заказа</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2>Ваш заказ подтвержден!</h2>
    <p>Заказ <strong>#{{ $orderId }}</strong> успешно зарезервирован и передан на сборку.</p>

    @if ($estimatedDelivery)
        <p>Ориентировочная дата доставки: <strong>{{ $estimatedDelivery }}</strong>.</p>
    @endif

    <p>Мы уведомим вас, когда статус доставки изменится.</p>
</body>
</html>
