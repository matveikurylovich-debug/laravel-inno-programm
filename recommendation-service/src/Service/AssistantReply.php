<?php

declare(strict_types=1);

namespace App\Service;

final class AssistantReply
{
    public static function sanitize(string $text, int $maxLength = 800): string
    {
        $withoutLinks = preg_replace('/\[[^\]]*\]\([^)]*\)/', '', $text) ?? $text;
        $withoutUrls = preg_replace('#(?:https?:)?//\S+#i', '', $withoutLinks) ?? $withoutLinks;
        $collapsed = trim((string) preg_replace('/[ \t]{2,}/', ' ', $withoutUrls));

        if (mb_strlen($collapsed) <= $maxLength) {
            return $collapsed;
        }

        return rtrim(mb_substr($collapsed, 0, $maxLength));
    }
}
