<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Converter;

use Publicplan\DocumentProcessor\Model\ConversionContext;
use PhpOffice\PhpWord\Element\TextBreak as DocBreak;

/**
 * Konvertiert Zeilenumbruch-Elemente in HTML.
 */
class BreakElementConverter implements ElementConverterInterface
{
    public function supports(object $element): bool
    {
        return $element instanceof DocBreak;
    }

    public function convert(object $element, ConversionContext $context): string
    {
        /** @var DocBreak $element */

        // Gelöschte Breaks ignorieren
        if ($element->getFontStyle()?->isStrikethrough()) {
            return '';
        }

        return '<br>' . PHP_EOL;
    }

    public function getPriority(): int
    {
        return 11; // Ganz niedrig, nur über Text
    }
}
