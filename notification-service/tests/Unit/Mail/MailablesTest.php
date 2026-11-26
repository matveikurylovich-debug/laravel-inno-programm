<?php

declare(strict_types=1);

use App\Mail\Auth\PasswordResetMail;
use App\Mail\Auth\TwoFactorCodeMail;
use App\Mail\Auth\UserRegisteredMail;
use App\Mail\Order\OrderCancelledMail;
use App\Mail\Order\OrderConfirmedMail;
use App\Mail\Order\OrderPaidMail;
use Illuminate\Support\Number;

it('renders all six mailables using the shared base layout and localized currency formatting', function (): void {
    expect(property_exists(OrderPaidMail::class, 'items'))->toBeFalse();

    $paid = new OrderPaidMail(orderId: 'ORD-101', amount: 1200.5, currency: 'BYN');
    $paidHtml = $paid->render();
    $expectedAmount = Number::currency(1200.5, in: 'BYN', locale: 'ru');

    expect($paid->envelope()->subject)->toBe('Заказ #ORD-101 успешно оплачен')
        ->and($paidHtml)->toContain('ORD-101')
        ->and($paidHtml)->toContain($expectedAmount)
        ->and($paidHtml)->toContain('Это автоматическое уведомление')
        ->and($paidHtml)->not->toContain('Состав заказа');

    $confirmed = new OrderConfirmedMail(
        orderId: 'ORD-102',
        estimatedDelivery: '2026-12-05',
        amount: 850,
        currency: 'RUB',
    );
    $confirmedHtml = $confirmed->render();

    expect($confirmed->envelope()->subject)->toBe('Заказ #ORD-102 подтверждён')
        ->and($confirmedHtml)->toContain('ORD-102')
        ->and($confirmedHtml)->toContain('2026-12-05')
        ->and($confirmedHtml)->toContain(Number::currency(850, in: 'RUB', locale: 'ru'))
        ->and($confirmedHtml)->toContain('Это автоматическое уведомление');

    $cancelled = new OrderCancelledMail(
        orderId: 'ORD-103',
        reason: 'нет в наличии на складе',
        refundedAmount: 500,
        currency: 'RUB',
    );
    $cancelledHtml = $cancelled->render();

    expect($cancelled->envelope()->subject)->toBe('Заказ #ORD-103 отменён')
        ->and($cancelledHtml)->toContain('ORD-103')
        ->and($cancelledHtml)->toContain('нет в наличии на складе')
        ->and($cancelledHtml)->toContain(Number::currency(500, in: 'RUB', locale: 'ru'))
        ->and($cancelledHtml)->toContain('Это автоматическое уведомление');

    $registered = new UserRegisteredMail(
        name: 'Анна',
        verificationUrl: 'https://shop.local/verify/abc',
    );
    $registeredHtml = $registered->render();

    expect($registeredHtml)->toContain('Здравствуйте, Анна!')
        ->and($registeredHtml)->toContain('https://shop.local/verify/abc')
        ->and($registeredHtml)->toContain('Это автоматическое уведомление');

    $reset = new PasswordResetMail(
        resetUrl: 'https://shop.local/reset/token-123',
        expiresIn: 30,
    );
    $resetHtml = $reset->render();

    expect($resetHtml)->toContain('https://shop.local/reset/token-123')
        ->and($resetHtml)->toContain('30 минут')
        ->and($resetHtml)->toContain('Это автоматическое уведомление');

    $twoFactor = new TwoFactorCodeMail(code: '987654', ttlMinutes: 7);
    $twoFactorHtml = $twoFactor->render();

    expect($twoFactorHtml)->toContain('987654')
        ->and($twoFactorHtml)->toContain('7 минут')
        ->and($twoFactorHtml)->toContain('Это автоматическое уведомление');
});
