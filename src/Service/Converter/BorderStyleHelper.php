<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Converter;

final class BorderStyleHelper
{
    private const MIN_WKHTMLTOPDF_BORDER_WIDTH_CM = 0.0264;

    public static function normalizeBorderWidthCm(float $widthCm): float
    {
        return max($widthCm, self::MIN_WKHTMLTOPDF_BORDER_WIDTH_CM);
    }

    public static function formatCssHexColor(?string $color, ?string $fallback = null): ?string
    {
        $normalizedColor = self::normalizeColor($color);
        if ($normalizedColor !== null) {
            return '#' . $normalizedColor;
        }

        $normalizedFallback = self::normalizeColor($fallback);
        if ($normalizedFallback !== null) {
            return '#' . $normalizedFallback;
        }

        return null;
    }

    private static function normalizeColor(?string $color): ?string
    {
        if ($color === null) {
            return null;
        }

        $normalized = ltrim(trim($color), '#');
        if ($normalized === '' || strtolower($normalized) === 'auto') {
            return null;
        }

        return $normalized;
    }
}
