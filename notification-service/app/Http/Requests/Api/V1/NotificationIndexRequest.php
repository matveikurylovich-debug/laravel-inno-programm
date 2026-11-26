<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Data\NotificationFilterData;
use App\Enums\NotificationStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class NotificationIndexRequest extends FormRequest
{
    /**
     * @return array<string, list<Enum|ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::enum(NotificationStatus::class)],
            'channel' => ['nullable', 'string', 'max:64'],
            'recipient' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): NotificationFilterData
    {
        $channel = $this->string('channel')->trim()->toString();
        $recipient = $this->string('recipient')->trim()->toString();

        return new NotificationFilterData(
            status: $this->enum('status', NotificationStatus::class),
            channel: $channel !== '' ? $channel : null,
            recipient: $recipient !== '' ? $recipient : null,
            dateFrom: $this->date('date_from')?->startOfDay(),
            dateTo: $this->date('date_to')?->endOfDay(),
            perPage: $this->integer('per_page', 15),
        );
    }
}
