<?php

declare(strict_types=1);

return [
    'accepted' => 'Поле «:attribute» должно быть принято.',
    'after_or_equal' => 'Поле «:attribute» должно быть датой после или равной «:date».',
    'array' => 'Поле «:attribute» должно быть массивом.',
    'before_or_equal' => 'Поле «:attribute» должно быть датой до или равной «:date».',
    'between' => [
        'numeric' => 'Поле «:attribute» должно быть между :min и :max.',
        'string' => 'Длина поля «:attribute» должна быть от :min до :max символов.',
    ],
    'boolean' => 'Поле «:attribute» должно иметь логическое значение.',
    'date' => 'Поле «:attribute» не является корректной датой.',
    'email' => 'Поле «:attribute» должно быть действительным email-адресом.',
    'enum' => 'Выбранное значение для «:attribute» некорректно.',
    'in' => 'Выбранное значение для «:attribute» некорректно.',
    'integer' => 'Поле «:attribute» должно быть целым числом.',
    'max' => [
        'numeric' => 'Поле «:attribute» не может быть больше :max.',
        'string' => 'Длина поля «:attribute» не может превышать :max символов.',
    ],
    'min' => [
        'numeric' => 'Поле «:attribute» должно быть не меньше :min.',
        'string' => 'Длина поля «:attribute» должна быть не меньше :min символов.',
    ],
    'numeric' => 'Поле «:attribute» должно быть числом.',
    'required' => 'Поле «:attribute» обязательно для заполнения.',
    'string' => 'Поле «:attribute» должно быть строкой.',
    'url' => 'Поле «:attribute» должно быть корректным URL.',

    'attributes' => [
        'status' => 'статус',
        'channel' => 'канал',
        'recipient' => 'получатель',
        'date_from' => 'дата начала',
        'date_to' => 'дата окончания',
        'per_page' => 'количество на странице',
        'email' => 'электронная почта',
    ],
];
