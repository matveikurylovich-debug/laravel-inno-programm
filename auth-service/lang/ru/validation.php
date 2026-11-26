<?php

declare(strict_types=1);

return [
    'accepted' => 'Необходимо принять поле :attribute.',
    'between' => [
        'array' => 'Количество элементов в поле :attribute должно быть от :min до :max.',
        'file' => 'Размер файла в поле :attribute должен быть от :min до :max килобайт.',
        'numeric' => 'Поле :attribute должно быть между :min и :max.',
        'string' => 'Количество символов в поле :attribute должно быть от :min до :max.',
    ],
    'boolean' => 'Поле :attribute должно иметь логическое значение.',
    'confirmed' => 'Подтверждение поля :attribute не совпадает.',
    'current_password' => 'Указан неверный текущий пароль.',
    'digits' => 'Поле :attribute должно содержать :digits цифр.',
    'email' => 'Поле :attribute должно быть действительным электронным адресом.',
    'enum' => 'Выбранное значение для :attribute некорректно.',
    'exists' => 'Выбранное значение для :attribute некорректно.',
    'in' => 'Выбранное значение для :attribute некорректно.',
    'integer' => 'Поле :attribute должно быть целым числом.',
    'lowercase' => 'Поле :attribute должно быть в нижнем регистре.',
    'max' => [
        'array' => 'Количество элементов в поле :attribute не может превышать :max.',
        'file' => 'Размер файла в поле :attribute не может быть больше :max килобайт.',
        'numeric' => 'Поле :attribute не может быть больше :max.',
        'string' => 'Количество символов в поле :attribute не может превышать :max.',
    ],
    'min' => [
        'array' => 'Количество элементов в поле :attribute должно быть не менее :min.',
        'file' => 'Размер файла в поле :attribute должен быть не менее :min килобайт.',
        'numeric' => 'Поле :attribute должно быть не менее :min.',
        'string' => 'Количество символов в поле :attribute должно быть не менее :min.',
    ],
    'numeric' => 'Поле :attribute должно быть числом.',
    'password' => [
        'letters' => 'Поле :attribute должно содержать хотя бы одну букву.',
        'mixed' => 'Поле :attribute должно содержать хотя бы одну заглавную и одну строчную букву.',
        'numbers' => 'Поле :attribute должно содержать хотя бы одну цифру.',
        'symbols' => 'Поле :attribute должно содержать хотя бы один символ.',
        'uncompromised' => 'Указанный :attribute обнаружен в утечках данных. Выберите другой :attribute.',
    ],
    'regex' => 'Поле :attribute имеет неверный формат.',
    'required' => 'Поле :attribute обязательно для заполнения.',
    'size' => [
        'array' => 'Количество элементов в поле :attribute должно быть :size.',
        'file' => 'Размер файла в поле :attribute должен быть :size килобайт.',
        'numeric' => 'Поле :attribute должно быть равно :size.',
        'string' => 'Количество символов в поле :attribute должно быть :size.',
    ],
    'string' => 'Поле :attribute должно быть строкой.',
    'unique' => 'Такое значение поля :attribute уже используется.',

    'attributes' => [
        'challenge_id' => 'идентификатор проверки',
        'code' => 'код подтверждения',
        'current_password' => 'текущий пароль',
        'email' => 'электронная почта',
        'name' => 'имя',
        'password' => 'пароль',
        'password_confirmation' => 'подтверждение пароля',
        'phone' => 'телефон',
        'refresh_token' => 'refresh-токен',
        'role' => 'роль',
        'token' => 'токен',
    ],
];
