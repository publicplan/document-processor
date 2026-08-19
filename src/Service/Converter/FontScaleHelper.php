<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Converter;

use PhpOffice\PhpWord\Style;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\Style\Paragraph;

class FontScaleHelper
{
    private const float SCALE_TOLERANCE = 0.001;

    public static function resolveFontSize(
        Font|string|null $fontStyle,
        Paragraph|string|null $paragraphStyle = null
    ): ?float
    {
        $visitedStyles = [];
        $resolved      = self::resolveFromFontReference($fontStyle, $visitedStyles);
        if ($resolved !== null) {
            return $resolved;
        }

        return self::resolveFromParagraphReference($paragraphStyle, $visitedStyles);
    }

    public static function createScaleAttribute(?float $effectiveFontSize, ?float $defaultFontSize): ?string
    {
        if ($effectiveFontSize === null || $defaultFontSize === null || $defaultFontSize <= 0.0) {
            return null;
        }

        $scale = round($effectiveFontSize / $defaultFontSize, 3);
        if (abs($scale - 1.0) < self::SCALE_TOLERANCE) {
            return null;
        }

        return sprintf('data-font-scale="%s"', self::formatScale($scale));
    }

    private static function formatScale(float $scale): string
    {
        $formatted = number_format($scale, 3, '.', '');
        return rtrim(rtrim($formatted, '0'), '.');
    }

    /**
     * @param array<string, bool> $visitedStyles
     */
    private static function resolveFromFontReference(Font|string|null $fontStyle, array &$visitedStyles): ?float
    {
        if ($fontStyle instanceof Font) {
            $size = $fontStyle->getSize();
            if ($size !== null) {
                return (float)$size;
            }

            $styleName = $fontStyle->getStyleName();
            if (is_string($styleName) && $styleName !== '') {
                $resolved = self::resolveFromNamedStyle($styleName, $visitedStyles);
                if ($resolved !== null) {
                    return $resolved;
                }
            }

            return self::resolveFromParagraphReference($fontStyle->getParagraph(), $visitedStyles);
        }

        if (is_string($fontStyle) && $fontStyle !== '') {
            return self::resolveFromNamedStyle($fontStyle, $visitedStyles);
        }

        return null;
    }

    /**
     * @param array<string, bool> $visitedStyles
     */
    private static function resolveFromParagraphReference(Paragraph|string|null $paragraphStyle, array &$visitedStyles): ?float
    {
        if ($paragraphStyle instanceof Paragraph) {
            $styleName = $paragraphStyle->getStyleName();
            if (is_string($styleName) && $styleName !== '') {
                $resolved = self::resolveFromNamedStyle($styleName, $visitedStyles);
                if ($resolved !== null) {
                    return $resolved;
                }
            }

            $basedOn = $paragraphStyle->getBasedOn();
            if (is_string($basedOn) && $basedOn !== '' && $basedOn !== $styleName) {
                return self::resolveFromNamedStyle($basedOn, $visitedStyles);
            }

            return null;
        }

        if (is_string($paragraphStyle) && $paragraphStyle !== '') {
            return self::resolveFromNamedStyle($paragraphStyle, $visitedStyles);
        }

        return null;
    }

    /**
     * @param array<string, bool> $visitedStyles
     */
    private static function resolveFromNamedStyle(string $styleName, array &$visitedStyles): ?float
    {
        if (isset($visitedStyles[$styleName])) {
            return null;
        }
        $visitedStyles[$styleName] = true;

        $style = Style::getStyle($styleName);
        if ($style instanceof Font) {
            $size = $style->getSize();
            if ($size !== null) {
                return (float)$size;
            }

            return self::resolveFromParagraphReference($style->getParagraph(), $visitedStyles);
        }

        if ($style instanceof Paragraph) {
            $basedOn = $style->getBasedOn();
            if ($basedOn !== '' && $basedOn !== $styleName) {
                return self::resolveFromNamedStyle($basedOn, $visitedStyles);
            }
        }

        return null;
    }
}
