@extends('emails.layout', ['subject' => 'Добро пожаловать в сервис!'])

@section('content')
    <h2>Здравствуйте, {{ $name }}!</h2>
    <p>Благодарим за регистрацию в нашей системе. Ваш аккаунт успешно создан.</p>

    @if(!empty($verificationUrl))
        <p>Для подтверждения вашего email-адреса перейдите по ссылке:</p>
        <p style="text-align: center;">
            <a href="{{ $verificationUrl }}" class="btn">Подтвердить аккаунт</a>
        </p>
    @endif

    <p>Если вы не регистрировались на нашем сайте, просто проигнорируйте это письмо.</p>
@endsection