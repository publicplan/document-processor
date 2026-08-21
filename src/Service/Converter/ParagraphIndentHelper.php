<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Converter;

use PhpOffice\PhpWord\Style;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\Style\Paragraph;

class ParagraphIndentHelper
{
    /**
     * @return array{indentLeft: ?float, hanging: ?float, firstLine: ?float}
     */
    public static function resolveEffectiveIndentation(Paragraph|string|null $paragraphStyle): array
    {
        $visitedStyles = [];
        return self::resolveFromParagraphReference($paragraphStyle, $visitedStyles);
    }

    /**
     * @param array<string, bool> $visitedStyles
     *
     * @return array{indentLeft: ?float, hanging: ?float, firstLine: ?float}
     */
    private static function resolveFromParagraphReference(
        Paragraph|string|null $paragraphStyle,
        array &$visitedStyles
    ): array {
        if ($paragraphStyle instanceof Paragraph) {
            $resolved  = self::extractDirectIndentation($paragraphStyle);
            $styleName = $paragraphStyle->getStyleName();
            if (is_string($styleName) && $styleName !== '') {
                $resolved = self::fillMissing($resolved, self::resolveFromNamedStyle($styleName, $visitedStyles));
            }

            $basedOn = $paragraphStyle->getBasedOn();
            if (is_string($basedOn) && $basedOn !== '' && $basedOn !== $styleName) {
                $resolved = self::fillMissing($resolved, self::resolveFromNamedStyle($basedOn, $visitedStyles));
            }

            return $resolved;
        }

        if (is_string($paragraphStyle) && $paragraphStyle !== '') {
            return self::resolveFromNamedStyle($paragraphStyle, $visitedStyles);
        }

        return ['indentLeft' => null, 'hanging' => null, 'firstLine' => null];
    }

    /**
     * @param array<string, bool> $visitedStyles
     *
     * @return array{indentLeft: ?float, hanging: ?float, firstLine: ?float}
     */
    private static function resolveFromNamedStyle(string $styleName, array &$visitedStyles): array
    {
        if (isset($visitedStyles[$styleName])) {
            return ['indentLeft' => null, 'hanging' => null, 'firstLine' => null];
        }
        $visitedStyles[$styleName] = true;

        $style = Style::getStyle($styleName);
        if ($style instanceof Paragraph) {
            $resolved = self::extractDirectIndentation($style);
            $basedOn  = $style->getBasedOn();
            if ($basedOn !== '' && $basedOn !== $styleName) {
                $resolved = self::fillMissing($resolved, self::resolveFromNamedStyle($basedOn, $visitedStyles));
            }

            return $resolved;
        }

        if ($style instanceof Font) {
            return self::resolveFromParagraphReference($style->getParagraph(), $visitedStyles);
        }

        return ['indentLeft' => null, 'hanging' => null, 'firstLine' => null];
    }

    /**
     * @return array{indentLeft: ?float, hanging: ?float, firstLine: ?float}
     */
    private static function extractDirectIndentation(Paragraph $paragraphStyle): array
    {
        return [
            'indentLeft' => $paragraphStyle->getIndentLeft(),
            'hanging'    => $paragraphStyle->getHanging(),
            'firstLine'  => $paragraphStyle->getIndentFirstLine(),
        ];
    }

    /**
     * @param array{indentLeft: ?float, hanging: ?float, firstLine: ?float} $base
     * @param array{indentLeft: ?float, hanging: ?float, firstLine: ?float} $fallback
     *
     * @return array{indentLeft: ?float, hanging: ?float, firstLine: ?float}
     */
    private static function fillMissing(array $base, array $fallback): array
    {
        return [
            'indentLeft' => $base['indentLeft'] ?? $fallback['indentLeft'],
            'hanging'    => $base['hanging'] ?? $fallback['hanging'],
            'firstLine'  => $base['firstLine'] ?? $fallback['firstLine'],
        ];
    }
}
