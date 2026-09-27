@extends('emails.layout', ['subject' => 'Код подтверждения входа'])

@section('content')
    <h2>Вход в аккаунт</h2>
    <p>Для подтверждения входа используйте одноразовый код:</p>

    <div class="code-box">
        {{ $code }}
    </div>

    <p>Код действует в течение {{ $ttlMinutes ?? 5 }} минут.</p>
    <p>Никому не сообщайте этот код.</p>
@endsection