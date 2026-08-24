<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service;

final class HtmlInlineTagSimplifier
{
    private const MERGEABLE_INLINE_TAG_PATTERN = 'strong|em|u|del|span|a';

    public function simplify(string $html): string
    {
        $pattern = sprintf(
            '#<(%1$s)(\b[^>]*)>(.*?)</\1><\1\2>(.*?)</\1>#si',
            self::MERGEABLE_INLINE_TAG_PATTERN
        );

        do {
            $previousHtml = $html;
            $html = preg_replace_callback(
                $pattern,
                static fn (array $matches): string => sprintf(
                    '<%1$s%2$s>%3$s%4$s</%1$s>',
                    $matches[1],
                    $matches[2],
                    $matches[3],
                    $matches[4]
                ),
                $html
            ) ?? $html;
        } while ($html !== $previousHtml);

        return $html;
    }
}
