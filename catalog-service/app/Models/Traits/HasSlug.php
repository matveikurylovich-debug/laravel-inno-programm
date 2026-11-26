<?php

declare(strict_types=1);

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::creating(function (Model $model): void {
            if (filled($model->getAttribute('slug'))) {
                return;
            }

            $name = $model->getAttribute('name');

            if (blank($name)) {
                return;
            }

            $model->setAttribute('slug', static::slugFromName((string) $name));
        });
    }

    public static function slugFromName(string $name): string
    {
        return Str::slug($name);
    }
}
