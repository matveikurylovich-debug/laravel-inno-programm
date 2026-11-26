<?php

declare(strict_types=1);

namespace App\Services\Search;

final class IndexAliasPlan
{
    /**
     * @param  list<string>  $currentIndexes
     * @return list<array<string, mixed>>
     */
    public static function actions(string $alias, string $newIndex, bool $aliasExists, array $currentIndexes, bool $concreteExists): array
    {
        $actions = [[
            'add' => [
                'index' => $newIndex,
                'alias' => $alias,
                'is_write_index' => true,
            ],
        ]];

        if ($aliasExists) {
            foreach ($currentIndexes as $index) {
                if ($index !== $newIndex) {
                    $actions[] = ['remove_index' => ['index' => $index]];
                }
            }

            return $actions;
        }

        if ($concreteExists) {
            $actions[] = ['remove_index' => ['index' => $alias]];
        }

        return $actions;
    }
}
